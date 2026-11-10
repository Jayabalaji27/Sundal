<?php

namespace App\Services\Ai\Tools;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;

/**
 * Writes a Knowledge Base article, as the Knowledge Base page does. Members
 * read published articles, and custom AI agents answer from them.
 */
class CreateKbArticleTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'create_kb_article';
    }

    public function description(): string
    {
        return 'Write a new Knowledge Base article (a how-to, policy or guide) in a category. It is saved as a draft unless the user asks to publish it. The article text is plain text. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['kb_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Article title, at most 255 characters.'],
            'content' => ['type' => 'string', 'required' => true, 'description' => 'The article text.'],
            'category' => ['type' => 'string', 'description' => 'Category name. Leave out when there is only one category, or ask.'],
            'publish' => ['type' => 'boolean', 'description' => 'True only when the user asked to publish it now.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The article needs a title of at most 255 characters.'));
        }
        $content = trim((string) ($args['content'] ?? ''));
        if ($content === '') {
            throw new ToolInputException(__('What should the article say? Ask the user.'));
        }
        if (mb_strlen($content) > 50000) {
            throw new ToolInputException(__('The article can be at most 50000 characters.'));
        }
        if ($this->resolver->articles($user)->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->exists()) {
            throw new ToolInputException(__('There is already an article called ":title". Ask the user whether to change that one (update_kb_article) instead.', ['title' => $title]));
        }
        $category = $this->category($user, $args['category'] ?? null);
        $publish = filter_var($args['publish'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new PreparedAction(
            summary: __('Write Knowledge Base article ":title"', ['title' => $title]),
            details: [
                __('Title') => $title,
                __('Category') => $category->name,
                __('Status') => $publish ? __('Published') : __('Draft'),
                __('Text') => mb_strimwidth($content, 0, 300, '…'),
            ],
            payload: ['title' => $title, 'content' => $content, 'kb_category_id' => $category->id, 'is_published' => $publish],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $category = $this->resolver->byId(KbCategory::query()->where('workspace_id', $user->current_workspace_id), (int) $payload['kb_category_id'], __('Knowledge Base category'));

        $article = KbArticle::create([
            'workspace_id' => $user->current_workspace_id,
            'created_by' => $user->id,
            'title' => $payload['title'],
            'content' => $payload['content'],
            'kb_category_id' => $category->id,
            'is_published' => (bool) $payload['is_published'],
        ]);

        return new ToolOutcome(
            __('Wrote Knowledge Base article ":title".', ['title' => $article->title]),
            $article,
            route('kb.articles.show', $article->id, false),
            ['article_id' => $article->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $article = $this->resolver->byId($this->resolver->articles($user), (int) $undo['article_id'], __('Knowledge Base article'));
        $article->delete();

        return __('Article ":title" removed.', ['title' => $article->title]);
    }

    private function category(User $user, ?string $ref): KbCategory
    {
        if ($ref !== null && trim($ref) !== '') {
            return $this->resolver->kbCategory($user, $ref);
        }

        $all = KbCategory::query()->where('workspace_id', $user->current_workspace_id)->orderBy('name')->get(['id', 'name']);

        return match ($all->count()) {
            0 => throw new ToolInputException(__('The Knowledge Base has no categories yet. Ask the user to create one on the Knowledge Base page first.')),
            1 => $all->first(),
            default => throw new ToolInputException(__('Which category should the article go in? The categories are: :list. Ask the user.', ['list' => $all->pluck('name')->implode('; ')])),
        };
    }
}
