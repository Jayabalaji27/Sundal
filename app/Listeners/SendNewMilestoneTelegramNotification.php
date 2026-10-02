<?php

namespace App\Listeners;

use App\Events\MilestoneCreated;
use App\Services\TelegramService;

class SendNewMilestoneTelegramNotification
{
    public function handle(MilestoneCreated $event): void
    {
        $milestone = $event->milestone;
        $userId = $milestone->created_by ?? auth()->id();
        $workspaceId = $milestone->project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('new_milestone', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Milestone Created',
                'message' => "A new milestone '{$milestone->title}' has been created.",
                'milestone_name' => $milestone->title,
                'project_name' => $milestone->project->title ?? 'Unknown Project',
                'due_date' => $milestone->due_date ?? 'No due date',
                'url' => route('projects.show', $milestone->project_id)
            ];

            TelegramService::send('new_milestone', $data, $userId, $workspaceId);
        }
    }
}