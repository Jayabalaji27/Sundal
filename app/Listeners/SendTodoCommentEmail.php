<?php

namespace App\Listeners;

use App\Events\TodoCommentAdded;
use App\Services\EmailTemplateService;
use Exception;

class SendTodoCommentEmail
{
    public function __construct(
        private EmailTemplateService $emailService
    ) {
    }

    public function handle(TodoCommentAdded $event): void
    {
        $todo = $event->todo;
        $activityType = $event->activityType;
        
        $todo->load(['creator', 'workspace', 'members']);

        if (!isEmailTemplateEnabled('Todo Comments Added', createdBy())) {
            return;
        }

        if ($todo->members && $todo->members->count() > 0) {
            session()->forget('email_error');
            
            foreach ($todo->members as $index => $member) {
                if ($index > 0) {
                    sleep(5);
                }
                
                $activityText = $activityType === 'comment' ? 'A new comment has been added' : 'A new attachment has been uploaded';
                
                $variables = [
                    '{app_url}' => config('app.url'),
                    '{todo_title}' => $todo->title,
                    '{todo_priority}' => ucfirst($todo->priority),
                    '{todo_status}' => ucfirst(str_replace('_', ' ', $todo->status)),
                    '{due_date}' => $todo->due_date ? date('F d, Y', strtotime($todo->due_date)) : 'No due date',
                    '{activity_type}' => ucfirst($activityType),
                    '{workspace_name}' => $todo->workspace->name ?? 'N/A',
                    '{created_by_name}' => $todo->creator->name,
                    '{member_name}' => $member->name,
                    '{app_name}' => config('app.name'),
                ];

                try {
                    $memberLanguage = $member->lang ?? 'en';
                    
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Todo Comments Added',
                        variables: $variables,
                        toEmail: $member->email,
                        toName: $member->name,
                        language: $memberLanguage
                    );
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                    if (!str_contains($errorMessage, 'Too many emails per second') &&
                        !str_contains($errorMessage, '550 5.7.0') &&
                        !str_contains($errorMessage, 'rate limit')) {
                        session()->flash('email_error', 'Failed to send todo comments added notification email: ' . $errorMessage);
                    }
                }
            }
        }
    }
}
