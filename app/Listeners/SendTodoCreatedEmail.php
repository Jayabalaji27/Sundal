<?php

namespace App\Listeners;

use App\Events\TodoCreated;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendTodoCreatedEmail
{
    public function __construct(
        private EmailTemplateService $emailService
    ) {
    }

    public function handle(TodoCreated $event): void
    {
        $todo = $event->todo;
        $createdBy = $todo->created_by;
        $memberLanguage = User::where('id', $createdBy)->pluck('lang')->first() ?? 'en';
        
        $todo->load(['creator', 'workspace', 'members']);

        if (!isEmailTemplateEnabled('Todo Created', createdBy())) {
            return;
        }

        if ($todo->members && $todo->members->count() > 0) {
            session()->forget('email_error');
            
            foreach ($todo->members as $index => $member) {
                if ($index > 0) {
                    sleep(5);
                }
                
                $variables = [
                    '{app_url}' => config('app.url'),
                    '{todo_title}' => $todo->title,
                    '{todo_description}' => $todo->description ?? 'No description',
                    '{todo_priority}' => ucfirst($todo->priority),
                    '{todo_status}' => ucfirst(str_replace('_', ' ', $todo->status)),
                    '{due_date}' => $todo->due_date ? $todo->due_date->format('Y-m-d') : 'Not set',
                    '{workspace_name}' => $todo->workspace->name ?? 'N/A',
                    '{created_by_name}' => $todo->creator->name,
                    '{member_name}' => $member->name,
                    '{app_name}' => config('app.name'),
                ];

                try {
                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Todo Created',
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
                        session()->flash('email_error', 'Failed to send todo notification email: ' . $errorMessage);
                    }
                }
            }
        }
    }
}
