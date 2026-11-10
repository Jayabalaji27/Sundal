<?php

namespace App\Actions\Projects;

use App\Events\MilestoneStatusUpdated;
use App\Models\ProjectMilestone;
use App\Models\User;

/**
 * Changes a milestone's title, description, due date or status. Shared by
 * ProjectMilestoneController and the AI assistant. Progress follows the
 * milestone's tasks, and a milestone moved to completed is stamped as
 * completed (date, who, 100%).
 *
 * $changes: any of title, description, due_date, status
 */
class UpdateMilestone
{
    public function handle(User $actor, ProjectMilestone $milestone, array $changes): ProjectMilestone
    {
        // The whole project: its progress is saved from the milestones (a lookup may have loaded only some columns).
        $milestone->unsetRelation('project');
        $oldStatus = $milestone->status;
        $milestone->update($changes);

        // Auto-calculate progress from tasks
        $milestone->updateProgressFromTasks();

        if ($milestone->status === 'completed' && $oldStatus !== 'completed') {
            $milestone->update(['progress' => 100, 'completed_at' => now(), 'completed_by' => $actor->id]);
        } elseif ($oldStatus === 'completed' && $milestone->status !== 'completed') {
            $milestone->update(['completed_at' => null, 'completed_by' => null]);
        }

        // Fire event for Slack notification if status changed
        if ($oldStatus !== $milestone->status && !config('app.is_demo', true)) {
            event(new MilestoneStatusUpdated($milestone, $oldStatus, $milestone->status));
        }

        $milestone->project->logActivity('milestone_updated', "Milestone '{$milestone->title}' was updated");

        return $milestone;
    }
}
