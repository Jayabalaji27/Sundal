<?php

namespace App\Actions\Projects;

use App\Events\MilestoneCreated;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;

/**
 * Adds a milestone at the end of a project's list. Shared by
 * ProjectMilestoneController and the AI assistant.
 *
 * $data: title, description?, due_date?, status (pending by default)
 */
class CreateMilestone
{
    public function handle(User $actor, Project $project, array $data): ProjectMilestone
    {
        $milestone = $project->milestones()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'progress' => 0,
            'order' => $project->milestones()->max('order') + 1,
            'created_by' => $actor->id,
        ]);

        // Calculate initial progress from tasks
        $milestone->updateProgressFromTasks();

        if (!config('app.is_demo', true)) {
            event(new MilestoneCreated($milestone));
        }

        $project->logActivity('milestone_created', "Milestone '{$milestone->title}' was created");

        return $milestone;
    }
}
