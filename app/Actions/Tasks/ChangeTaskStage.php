<?php

namespace App\Actions\Tasks;

use App\Events\TaskStageUpdated;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskStage;
use App\Models\User;

/**
 * Moves a task to another stage. Shared by TaskController and the AI
 * assistant. Permission and workspace checks stay with the caller; the
 * process rules below apply to every caller:
 *
 *  - An unassigned task stays in the first stage until someone is assigned.
 *  - Moving back (to a stage earlier in the order) needs a reason, saved as a
 *    comment on the task. Leaving a "Blocked"/"On hold" stage is not a move back.
 *  - Only the workspace owner or a manager can reopen a task from a completed stage.
 */
class ChangeTaskStage
{
    /** Stages that park a task rather than mark progress; leaving one is never "back". */
    public const PARKING_STAGE_NAMES = ['blocked', 'on hold', 'on-hold', 'hold'];

    /**
     * @param bool $enforceRules false only for undoing the actor's own move (AI undo).
     * @throws StageMoveRefused
     */
    public function handle(User $actor, Task $task, TaskStage $stage, ?string $reason = null, bool $enforceRules = true): Task
    {
        $task->loadMissing('taskStage');
        $from = $task->taskStage;
        $reason = trim((string) $reason) ?: null;

        if ($enforceRules) {
            $this->check($actor, $task, $from, $stage, $reason);
        }

        $oldStage = $from->name ?? 'Unknown';

        $task->update(['task_stage_id' => $stage->id]);
        $task->setRelation('taskStage', $stage);

        if ($reason && $from && $this->isBackward($from, $stage)) {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => $actor->id,
                'comment' => __('Moved back from :from to :to. Reason: :reason', ['from' => $from->name, 'to' => $stage->name, 'reason' => $reason]),
                'mentions' => [],
            ]);
            $this->attachReasonToHistory($task, $reason, $comment->id);
        }

        if (!config('app.is_demo', true)) {
            event(new TaskStageUpdated($task, $oldStage, $stage->name));
        }

        return $task;
    }

    /** @throws StageMoveRefused */
    public function check(User $actor, Task $task, ?TaskStage $from, TaskStage $to, ?string $reason): void
    {
        if ($from && (int) $from->id === (int) $to->id) {
            return;
        }

        $first = TaskStage::where('workspace_id', $to->workspace_id)->orderBy('order')->orderBy('id')->first();
        $unassigned = !$task->assigned_to && !$task->members()->exists();
        if ($unassigned && $first && (int) $to->id !== (int) $first->id) {
            throw new StageMoveRefused(__('Assign this task to someone before moving it to :stage.', ['stage' => $to->name]));
        }

        if (!$from) {
            return;
        }

        if ($this->isCompleted($from) && !$this->isCompleted($to) && !$this->canReopen($actor)) {
            throw new StageMoveRefused(__('Only the workspace owner or a manager can reopen a task from :stage.', ['stage' => $from->name]));
        }

        if ($this->isBackward($from, $to) && !$reason) {
            throw new StageMoveRefused(
                __('Moving a task back from :from to :to needs a reason.', ['from' => $from->name, 'to' => $to->name]),
                needsReason: true,
            );
        }
    }

    /**
     * The History tab shows the reason on the stage change itself (and hides the
     * comment that carries it), so the reason goes onto the history entry too.
     */
    private function attachReasonToHistory(Task $task, string $reason, int $commentId): void
    {
        try {
            $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', $task->getMorphClass())
                ->where('subject_id', $task->id)->where('event', 'updated')->latest('id')->first();
            if ($activity && isset($activity->properties['attributes']['task_stage_id'])) {
                $activity->properties = $activity->properties->merge(['reason' => $reason, 'reason_comment_id' => $commentId]);
                $activity->save();
            }
        } catch (\Throwable $e) {
            report($e); // history must never block the move
        }
    }

    public function isBackward(TaskStage $from, TaskStage $to): bool
    {
        if (in_array(mb_strtolower(trim($from->name)), self::PARKING_STAGE_NAMES, true)) {
            return false;
        }

        return [$to->order, $to->id] < [$from->order, $from->id];
    }

    private function isCompleted(TaskStage $stage): bool
    {
        return (bool) $stage->is_completed
            || in_array(mb_strtolower(trim($stage->name)), TaskStage::COMPLETED_STAGE_NAMES, true);
    }

    private function canReopen(User $actor): bool
    {
        $workspace = $actor->currentWorkspace;
        if (!$workspace || $workspace->isOwner($actor)) {
            return true;
        }

        return !in_array($workspace->getMemberRole($actor), ['member', 'client'], true);
    }
}
