<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Services\TelegramService;

class SendNewProjectTelegramNotification
{
    public function handle(ProjectCreated $event): void
    {
        $project = $event->project;
        $userId = $project->created_by ?? auth()->id();
        $workspaceId = $project->workspace_id ?? null;
        
        if (!$userId) return;

        if (isTelegramNotificationEnabled('new_project', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Project Created',
                'message' => "A new project '{$project->title}' has been created.",
                'project_name' => $project->title,
                'created_by' => $project->creator->name ?? 'Unknown User',
                'url' => route('projects.show', $project->id)
            ];

            TelegramService::send('new_project', $data, $userId, $workspaceId);
        }
    }
}