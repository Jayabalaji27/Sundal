<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    use BelongsToWorkspace;

    protected $table = 'ai_usage';

    protected $fillable = [
        'workspace_id', 'user_id', 'ai_conversation_id', 'provider', 'model', 'input_tokens', 'output_tokens',
    ];

    public static function tokensThisMonth(int $workspaceId): int
    {
        return (int) static::withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as total')
            ->value('total');
    }

    /** @return array<int, array{date: string, tokens: int}> one row per day, oldest first, zero-filled */
    public static function dailyTokens(int $workspaceId, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $totals = static::withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(input_tokens + output_tokens) as tokens')
            ->groupBy('day')
            ->pluck('tokens', 'day');

        return collect(range(0, $days - 1))
            ->map(fn (int $i) => $from->copy()->addDays($i)->toDateString())
            ->map(fn (string $day) => ['date' => $day, 'tokens' => (int) ($totals[$day] ?? 0)])
            ->all();
    }
}
