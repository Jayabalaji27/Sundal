<?php

namespace App\Listeners;

use App\Events\WorkspaceInvited;
use App\Models\User;
use App\Services\EmailTemplateService;
use Exception;

class SendWorkspaceInvitationEmail
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
    public function handle(WorkspaceInvited $event): void
    {
        $invitation = $event->invitation;
        $workspace = $invitation->workspace;
        $invitedBy = $invitation->invitedBy;

        if (isEmailTemplateEnabled('Workspace Invitation', createdBy())) {
            // Prepare email variables
            $variables = [
                '{workspace_name}' => $workspace->name ?? '-',
                '{invited_by_name}' => $invitedBy->name ?? '-',
                '{invitee_email}' => $invitation->email ?? '-',
                '{user_name}' => $invitation->email ?? '-',
                '{role}' => ucfirst($invitation->role) ?? '-',
                '{invitation_link}' => route('invitations.show', $invitation->token),
                '{company_name}' => config('app.name'),
                '{app_name}' => config('app.name'),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');

                // Send workspace invitation email
                $userLanguage = (auth()->user() && auth()->user()->lang) ? auth()->user()->lang : 'en';
                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'Workspace Invitation',
                    variables: $variables,
                    toEmail: $invitation->email,
                    toName: $invitation->email,
                    language: $userLanguage,
                    business: $workspace,
                );
            } catch (Exception $e) {
                $errorMessage = $e->getMessage();
                \Log::error('Failed to send workspace invitation email.', [
                    'invitation_id' => $invitation->id,
                    'workspace_id' => $workspace->id ?? null,
                    'error' => $errorMessage,
                ]);
                if (!str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')) {
                    session()->flash('email_error', 'Failed to send workspace invitation email: ' . $errorMessage);
                }
            }
        }
    }
}