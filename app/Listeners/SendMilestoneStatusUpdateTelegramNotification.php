<?php

namespace App\Listeners;

use App\Events\MilestoneStatusUpdated;
use App\Services\TelegramService;

class SendMilestoneStatusUpdateTelegramNotification
{
    public function handle(MilestoneStatusUpdated $event): void
    {
        $milestone = $event->milestone;
        $userId = $milestone->created_by ?? auth()->id();
        $workspaceId = $milestone->project->workspace_id ?? null;

        if (!$userId)
            return;
        if (isTelegramNotificationEnabled('milestone_status_updated', $userId, $workspaceId)) {

            $data = [
                'title' => 'Milestone Status Updated',
                'message' => "Milestone '{$milestone->title}' status has been updated to '{$milestone->status}'.",
                'milestone_name' => $milestone->title,
                'project_name' => $milestone->project->title ?? 'Unknown Project',
                'new_status' => $milestone->status,
                'url' => route('projects.show', $milestone->project_id)
            ];

            TelegramService::send('milestone_status_updated', $data, $userId, $workspaceId);
        }
    }
}