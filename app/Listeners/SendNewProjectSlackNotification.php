<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use App\Services\SlackService;

class SendNewProjectSlackNotification
{
    public function handle(ProjectCreated $event): void
    {        
        $project = $event->project;
        $userId = $project->created_by ?? auth()->id();
        $workspaceId = $project->workspace_id;
        
        if (!$userId) {
            return;
        }

        if (isSlackNotificationEnabled('new_project', $userId, $workspaceId)) {
            $data = [
                'title' => 'New Project Created',
                'message' => "A new project '{$project->title}' has been created.",
                'project_name' => $project->title,
                'url' => route('projects.show', $project->id)
            ];
            SlackService::send('new_project', $data, $userId, $workspaceId);
        }
    }
}