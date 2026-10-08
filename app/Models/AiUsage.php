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
}
