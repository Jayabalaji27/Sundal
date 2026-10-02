<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Services\EmailTemplateService;
use Exception;

class SendTaskAssignmentEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TaskAssigned $event): void
    {
        $task = $event->task;
        $assignedUser = $event->assignedUser;
        $assignedBy = $event->assignedBy;

        if (isEmailTemplateEnabled('Task Assignment', createdBy())) {
            // Prepare email variables
            $variables = [
                '{task_title}' => $task->title ?? '-',
                '{project_name}' => $task->project->title ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_by_name}' => $assignedBy->name ?? '-',
                '{task_description}' => $task->description ?? '-',
                '{task_priority}' => ucfirst($task->priority) ?? '-',
                '{start_date}' => $task->start_date ? $task->start_date->format('M d, Y') : '-',
                '{end_date}' => $task->end_date ? $task->end_date->format('M d, Y') : '-',
                '{company_name}' => config('app.name'),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send task assignment email
                $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Task Assignment',
                    variables: $variables,
                    toEmail: $assignedUser->email,
                    toName: $assignedUser->name,
                    language: $userLanguage
                );
            } catch (Exception $e) {
                // Only store error if it's not a rate limiting issue
                $errorMessage = $e->getMessage();
                if (!str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')) {
                    session()->flash('email_error', 'Failed to send task assignment email: ' . $errorMessage);
                }
            }
        }
    }
}