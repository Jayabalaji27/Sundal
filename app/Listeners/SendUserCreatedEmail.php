<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\EmailTemplateService;
use App\Services\WebhookService;
use Exception;

class SendUserCreatedEmail
{
    private static array $processedUsers = [];
    
    public function __construct(
        private EmailTemplateService $emailService,
        private WebhookService $webhookService
    ) {
    }

    public function handle(UserCreated $event): void
    {
        $user = $event->user;
        $plainPassword = $event->plainPassword;

        // Prevent duplicate processing
        $userKey = $user->id . '_' . $user->updated_at->timestamp;
        if (in_array($userKey, self::$processedUsers)) {
            return;
        }
        
        self::$processedUsers[] = $userKey;

        if (isEmailTemplateEnabled('User Created', createdBy())) {
            // Prepare email variables
            $variables = [
                '{app_url}' => config('app.url'),
                '{user_name}' => $user->name,
                '{user_email}' => $user->email,
                '{user_password}' => $plainPassword ?: 'Password set by user',
                '{user_type}' => ucfirst($user->type),
                '{app_name}' => config('app.name'),
                '{created_date}' => $user->created_at->format('Y-m-d H:i:s'),
            ];

            try {
                // Clear any existing email error
                session()->forget('email_error');
                // Send welcome email to the newly created user in their language
                $userLanguage = $user->lang ?? 'en';
                $this->emailService->sendTemplateEmailWithLanguage(
                    templateName: 'User Created',
                    variables: $variables,
                    toEmail: $user->email,
                    toName: $user->name,
                    language: $userLanguage
                );

            } catch (Exception $e) {
                // Only store error if it's not a rate limiting issue
                $errorMessage = $e->getMessage();
                if (!str_contains($errorMessage, 'Too many emails per second') &&
                    !str_contains($errorMessage, '550 5.7.0') &&
                    !str_contains($errorMessage, 'rate limit')) {
                    session()->flash('email_error', 'Failed to send welcome email: ' . $errorMessage);
                }
            }
        }
    }
}