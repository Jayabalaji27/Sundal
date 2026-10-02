<?php

namespace App\Listeners;

use App\Events\TodoStatusUpdated;
use App\Services\EmailTemplateService;
use Exception;

class SendTodoStatusUpdatedEmail
{
    public function __construct(
        private EmailTemplateService $emailService
    ) {
    }

    public function handle(TodoStatusUpdated $event): void
    {
        $todo = $event->todo;
        
        $todo->load(['creator', 'workspace', 'members']);

        // Always use todo creator's (company's) email settings
        if (!isEmailTemplateEnabled('Todo Status Updated', $todo->creator->id)) {
            return;
        }
        
        $currentUserId = auth()->id();
        if ($todo->members && $todo->members->count() > 0) {
            session()->forget('email_error');
            
            $recipients = collect();
            
            // Add creator (company) if not the one updating
            if ($todo->creator->id !== $currentUserId) {
                $recipients->push($todo->creator);
            }
            
            // Add all assigned members except the one updating
            foreach ($todo->members as $member) {
                if ($member->id !== $currentUserId && !$recipients->contains('id', $member->id)) {
                    $recipients->push($member);
                }
            }
            
            foreach ($recipients as $index => $recipient) {
                if ($index > 0) {
                    sleep(5);
                }
                
                $variables = [
                    '{app_url}' => config('app.url'),
                    '{todo_title}' => $todo->title,
                    '{todo_description}' => $todo->description ?? 'No description',
                    '{todo_priority}' => ucfirst($todo->priority),
                    '{old_status}' => ucfirst(str_replace('_', ' ', $event->oldStatus)),
                    '{new_status}' => ucfirst(str_replace('_', ' ', $todo->status)),
                    '{due_date}' => $todo->due_date ? $todo->due_date->format('Y-m-d') : 'Not set',
                    '{workspace_name}' => $todo->workspace->name ?? 'N/A',
                    '{updated_by_name}' => auth()->user()->name ?? $todo->creator->name,
                    '{member_name}' => $recipient->name,
                    '{app_name}' => config('app.name'),
                ];

                try {
                    $memberLanguage = $recipient->lang ?? 'en';
                    
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Todo Status Updated',
                        variables: $variables,
                        toEmail: $recipient->email,
                        toName: $recipient->name,
                        language: $memberLanguage,
                        creatorId: $todo->creator->id
                    );
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                    if (!str_contains($errorMessage, 'Too many emails per second') &&
                        !str_contains($errorMessage, '550 5.7.0') &&
                        !str_contains($errorMessage, 'rate limit')) {
                        session()->flash('email_error', 'Failed to send todo status update notification email: ' . $errorMessage);
                    }
                }
            }
        }
    }
}
