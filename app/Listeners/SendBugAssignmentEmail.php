<?php

namespace App\Listeners;

use App\Events\BugAssigned;
use App\Services\EmailTemplateService;
use Exception;

class SendBugAssignmentEmail
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
    public function handle(BugAssigned $event): void
    {
        $bug = $event->bug;
        $assignedUser = $event->assignedUser;
        $assignedBy = $event->assignedBy;

        if (isEmailTemplateEnabled('Bug Assignment', createdBy())) {
            // Prepare email variables
            $variables = [
                '{bug_title}' => $bug->title ?? '-',
                '{project_name}' => $bug->project->title ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_by_name}' => $assignedBy->name ?? '-',
                '{bug_description}' => $bug->description ?? '-',
                '{bug_priority}' => ucfirst($bug->priority) ?? '-',
                '{bug_severity}' => ucfirst($bug->severity) ?? '-',
                '{start_date}' => $bug->created_at ? $bug->created_at->format('M d, Y') : '-',
                '{end_date}' => $bug->due_date ? $bug->due_date->format('M d, Y') : ($bug->updated_at ? $bug->updated_at->format('M d, Y') : '-'),
                '{company_name}' => config('app.name'),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send bug assignment email
                $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Bug Assignment',
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
                    session()->flash('email_error', 'Failed to send bug assignment email: ' . $errorMessage);
                }
            }
        }
    }
}