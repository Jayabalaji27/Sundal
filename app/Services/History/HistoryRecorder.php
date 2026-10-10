<?php

namespace App\Services\History;

use App\Models;
use App\Services\Ai\AiActionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One history log for every business module: who created, changed or
 * deleted which record, the old and new values, and whether it came from a
 * normal screen or the AI assistant. Stored with spatie/laravel-activitylog.
 *
 * Done with model event listeners rather than Spatie's LogsActivity trait,
 * because several models already use App\Traits\LogsActivity (the project
 * feed) and the two traits' boot methods share a name.
 */
class HistoryRecorder
{
    /** @var class-string<Model>[] */
    public const MODELS = [
        Models\Project::class,
        Models\ProjectMember::class,
        Models\ProjectMilestone::class,
        Models\Task::class,
        Models\TaskComment::class,
        Models\Bug::class,
        Models\BugComment::class,
        Models\Sprint::class,
        Models\Timesheet::class,
        Models\TimesheetEntry::class,
        Models\TimesheetApproval::class,
        Models\ProjectExpense::class,
        Models\ExpenseApproval::class,
        Models\ProjectBudget::class,
        Models\Invoice::class,
        Models\Payment::class,
        Models\Contract::class,
        Models\Note::class,
        Models\Todo::class,
        Models\KbArticle::class,
        Models\WorkspaceInvitation::class,
        Models\WorkspaceMember::class,
    ];

    /** Never written to the log, whatever the model. */
    private const SECRET = ['password', 'remember_token', 'token', 'payment_token', 'api_key', 'two_factor_secret'];

    /** Bookkeeping columns that change on every save. */
    private const NOISE = ['updated_at', 'created_at'];

    public static function register(): void
    {
        foreach (self::MODELS as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $class::created(fn (Model $model) => self::record($model, 'created'));
            $class::updated(fn (Model $model) => self::record($model, 'updated'));
            $class::deleted(fn (Model $model) => self::record($model, 'deleted'));
        }
    }

    public static function record(Model $model, string $event): void
    {
        $changes = self::changes($model, $event);
        if ($event === 'updated' && empty($changes['attributes'])) {
            return;
        }

        try {
            $logger = activity(Str::snake(class_basename($model)))->performedOn($model);
            if (auth()->check()) {
                $logger->causedBy(auth()->user());
            }

            // Same shape as Spatie's own model logging: properties.attributes
            // (new values) and properties.old.
            $logger->event($event)
                ->withProperties($changes + array_filter([
                    // ai_assistant: a confirmed AI card; screen: a signed-in user;
                    // system: no user (seeder, scheduled job).
                    'source' => AiActionContext::metadata() ? 'ai_assistant' : (auth()->check() ? 'screen' : 'system'),
                    'ai_tool_call_id' => AiActionContext::metadata()['ai_tool_call_id'] ?? null,
                ]))
                ->tap(function ($activity) use ($model) {
                    $activity->workspace_id = self::workspaceId($model);
                })
                ->log($event);
        } catch (\Throwable $e) {
            // History must never break the action being recorded.
            report($e);
        }
    }

    /** @return array{attributes?: array, old?: array} */
    private static function changes(Model $model, string $event): array
    {
        $hidden = array_merge(self::SECRET, $model->getHidden());
        $clean = fn (array $values) => collect($values)
            ->except(array_merge($hidden, self::NOISE))
            ->map(fn ($v) => is_string($v) && mb_strlen($v) > 500 ? mb_substr($v, 0, 500) . '…' : $v)
            ->all();

        return match ($event) {
            'created' => ['attributes' => $clean($model->getAttributes())],
            'deleted' => ['old' => $clean($model->getAttributes())],
            default => [
                'attributes' => $clean($model->getChanges()),
                'old' => $clean(array_intersect_key($model->getOriginal(), $model->getChanges())),
            ],
        };
    }

    private static function workspaceId(Model $model): ?int
    {
        if (isset($model->workspace_id)) {
            return (int) $model->workspace_id;
        }
        if (isset($model->project_id) && method_exists($model, 'project')) {
            return $model->project?->workspace_id;
        }

        return auth()->user()?->current_workspace_id;
    }
}
