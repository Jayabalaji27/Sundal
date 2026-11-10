<?php

namespace App\Services\Ai\Tools;

use App\Models\KbArticle;
use App\Models\User;

/** Changes a Knowledge Base article: title, text, category, published or draft. */
class UpdateKbArticleTool extends AiTool
{
    use RevertsChanges;

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_kb_article';
    }

    public function description(): string
    {
        return 'Change a Knowledge Base article: its title, its text (replace it, or add to the end), its category, or publish / unpublish it. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['kb_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'article' => ['type' => 'string', 'required' => true, 'description' => 'Article title or id.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'content' => ['type' => 'string', 'description' => 'New text, replacing the whole article.'],
            'append' => ['type' => 'string', 'description' => 'Text to add at the end of the article.'],
            'category' => ['type' => 'string', 'description' => 'New category name.'],
            'publish' => ['type' => 'boolean', 'description' => 'True to publish, false to make it a draft again.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var KbArticle $article */
        $article = $this->resolver->article($user, (string) ($args['article'] ?? ''));

        $content = isset($args['content']) && trim((string) $args['content']) !== '' ? trim((string) $args['content']) : null;
        if (isset($args['append']) && trim((string) $args['append']) !== '') {
            $content = rtrim($content ?? (string) $article->content) . "\n\n" . trim((string) $args['append']);
        }
        $category = !empty($args['category']) ? $this->resolver->kbCategory($user, (string) $args['category']) : null;

        $changes = [
            'title' => isset($args['title']) && trim((string) $args['title']) !== '' ? trim((string) $args['title']) : null,
            'content' => $content,
            'kb_category_id' => $category?->id,
            'is_published' => array_key_exists('publish', $args) && $args['publish'] !== null && $args['publish'] !== ''
                ? (int) filter_var($args['publish'], FILTER_VALIDATE_BOOLEAN) : null,
        ];
        if (isset($changes['title']) && mb_strlen($changes['title']) > 255) {
            throw new ToolInputException(__('The article needs a title of at most 255 characters.'));
        }
        if (isset($changes['content']) && mb_strlen($changes['content']) > 50000) {
            throw new ToolInputException(__('The article can be at most 50000 characters.'));
        }

        $old = $this->snapshot($article, array_keys($changes));
        $changes = array_filter($changes, fn ($value, $field) => $value !== null && (string) $value !== (string) ($field === 'kb_category_id' ? (int) ($old[$field] ?? 0) : ($old[$field] ?? '')), ARRAY_FILTER_USE_BOTH);
        if ($changes === []) {
            throw new ToolInputException(__('What should change on article ":title"? Ask the user.', ['title' => $article->title]));
        }

        $article->loadMissing('category:id,name');

        return new PreparedAction(
            summary: __('Change Knowledge Base article ":title"', ['title' => $article->title]),
            details: [
                __('Article') => $article->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => match ($field) {
                    'title' => [__('Title') => "{$article->title} → {$value}"],
                    'content' => [__('Text') => mb_strimwidth((string) $value, 0, 300, '…')],
                    'kb_category_id' => [__('Category') => ($article->category?->name ?? '—') . ' → ' . $category->name],
                    'is_published' => [__('Status') => $value ? __('Published') : __('Draft')],
                })->all(),
            ],
            payload: ['article_id' => $article->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var KbArticle $article */
        $article = $this->resolver->byId($this->resolver->articles($user), (int) $payload['article_id'], __('Knowledge Base article'));
        $before = $this->snapshot($article, array_keys($payload['changes']));

        $article->update($payload['changes']);

        return new ToolOutcome(
            __('Updated article ":title".', ['title' => $article->title]),
            $article,
            route('kb.articles.show', $article->id, false),
            $this->changeUndo($article, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var KbArticle $article */
        $article = $this->resolver->byId($this->resolver->articles($user), (int) $undo['id'], __('Knowledge Base article'));
        $this->ensureUnchanged($article, $undo, __('Article ":title"', ['title' => $article->title]));

        $article->update($undo['before']);

        return __('Article ":title" put back.', ['title' => $article->title]);
    }
}
