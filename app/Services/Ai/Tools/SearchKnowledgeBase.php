<?php

namespace App\Services\Ai\Tools;

use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * "How do I…" answers from the workspace's own Knowledge Base articles.
 * Plain keyword search over published articles; no vector index needed yet.
 */
class SearchKnowledgeBase extends AiTool
{
    public function name(): string
    {
        return 'search_knowledge_base';
    }

    public function description(): string
    {
        return 'Search this workspace\'s Knowledge Base articles to answer "how do I…" and policy questions. Answer only from the returned text and link the article.';
    }

    public function permissions(): array
    {
        return ['kb_view_any', 'kb_view'];
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'required' => true, 'description' => 'Key words to search for.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $words = collect(preg_split('/\s+/', mb_strtolower(trim((string) ($args['query'] ?? '')))))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->take(8);

        if ($words->isEmpty()) {
            throw new ToolInputException(__('Give a few key words to search the Knowledge Base for.'));
        }

        $articles = KbArticle::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->where('is_published', true)
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $like = '%' . addcslashes($word, '%_\\') . '%';
                    $q->orWhere('title', 'like', $like)->orWhere('content', 'like', $like);
                }
            })
            ->limit(20)
            ->get(['id', 'title', 'content']);

        // Rank by how many search words each article contains, title hits counting double.
        $ranked = $articles->map(function (KbArticle $a) use ($words) {
            $title = mb_strtolower($a->title);
            $body = mb_strtolower(strip_tags((string) $a->content));
            $score = $words->sum(fn ($w) => (str_contains($title, $w) ? 2 : 0) + (str_contains($body, $w) ? 1 : 0));

            return ['article' => $a, 'score' => $score, 'body' => $body];
        })->sortByDesc('score')->take(3);

        return [
            'articles' => $ranked->map(fn ($r) => [
                'title' => $r['article']->title,
                'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $r['article']->content))), 1500),
                'link' => route('kb.articles.show', $r['article']->id, false),
            ])->values()->all(),
            'found' => $ranked->count(),
        ];
    }
}
