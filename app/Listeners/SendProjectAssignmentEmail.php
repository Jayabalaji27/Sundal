<?php

namespace App\Listeners;

use App\Events\ProjectMemberAssigned;
use App\Services\EmailTemplateService;
use Exception;

class SendProjectAssignmentEmail
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
    public function handle(ProjectMemberAssigned $event): void
    {
        $project = $event->project;
        $assignedUser = $event->assignedUser;
        $assignedBy = $event->assignedBy;
        $role = $event->role;

        if (isEmailTemplateEnabled('Project Assignment', createdBy())) {
            // Prepare email variables
            $variables = [
                '{project_name}' => $project->title ?? '-',
                '{assigned_user_name}' => $assignedUser->name ?? '-',
                '{assigned_by_name}' => $assignedBy->name ?? '-',
                '{role}' => ucfirst($role) ?? '-',
                '{project_description}' => $project->description ?? '-',
                '{company_name}' => config('app.name'),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send project assignment email
                $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';

                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Project Assignment',
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
                    session()->flash('email_error', 'Failed to send project assignment email: ' . $errorMessage);
                }
            }
        }
    }
}