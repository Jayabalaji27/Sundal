<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's own AI vendor account (BYOA). One row per workspace.
 * The API key is encrypted at rest and hidden from serialization.
 */
class AiProviderSetting extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'provider', 'model', 'api_key', 'api_key_last4',
        'azure_endpoint', 'azure_deployment', 'organization', 'monthly_token_cap',
        'managers_enabled', 'retention_days', 'idle_timeout_minutes', 'last_tested_at', 'last_test_passed', 'updated_by',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'managers_enabled' => 'boolean',
        'monthly_token_cap' => 'integer',
        'retention_days' => 'integer',
        'idle_timeout_minutes' => 'integer',
        'last_tested_at' => 'datetime',
        'last_test_passed' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function maskedKey(): string
    {
        return $this->api_key_last4 ? '••••' . $this->api_key_last4 : '••••';
    }

    public function isReadOnlyModel(): bool
    {
        return (bool) config("ai_assistant.providers.{$this->provider}.models.{$this->model}.read_only", false);
    }
}
