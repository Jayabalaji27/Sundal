<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    /**
     * Emails that are on for a company until its owner switches them off in
     * Settings → Email Notifications. Every other email starts off.
     */
    public const DEFAULT_ON = [\App\Support\EmailTemplates\CommentMentionTemplate::NAME];

    /** Whether the email is on for the user (and workspace), using DEFAULT_ON when they never chose. */
    public static function isOnFor(string $name, ?int $userId, ?int $workspaceId = null, bool $anyWorkspace = false): bool
    {
        $template = static::where('name', $name)->first();
        if (!$template || !$userId) {
            return false;
        }

        $choice = UserEmailTemplate::where('user_id', $userId)
            ->where('template_id', $template->id)
            ->when(!$anyWorkspace, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->first();

        return $choice ? (bool) $choice->is_active : in_array($name, self::DEFAULT_ON, true);
    }

    protected $fillable = [
        'name',
        'from',
        'user_id',
    ];

    public function emailTemplateLangs(): HasMany
    {
        return $this->hasMany(EmailTemplateLang::class, 'parent_id');
    }

    public function userEmailTemplates(): HasMany
    {
        return $this->hasMany(UserEmailTemplate::class, 'template_id');
    }
}
