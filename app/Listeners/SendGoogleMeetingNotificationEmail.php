<?php

namespace App\Listeners;

use App\Events\GoogleMeetingCreated;
use App\Services\EmailTemplateService;
use Exception;

class SendGoogleMeetingNotificationEmail
{
    public function __construct(
        private EmailTemplateService $emailService,
    ) {
    }

    public function handle(GoogleMeetingCreated $event): void
    {
        $meeting = $event->meeting;

        if ($meeting->members && $meeting->members->count() > 0) {
            session()->forget('email_error');
            
            foreach ($meeting->members as $index => $member) {
                if (!$member->email) {
                    continue;
                }
                
                if ($index > 0) {
                    sleep(5);
                }

                $variables = [
                    '{member_name}' => $member->name,
                    '{meeting_title}' => $meeting->title,
                    '{project_name}' => $meeting->project->title ?? 'N/A',
                    '{start_time}' => $meeting->start_time->format('Y-m-d H:i:s'),
                    '{duration}' => $meeting->duration,
                    '{organizer_name}' => $meeting->user->name,
                    '{meeting_description}' => $meeting->description ?? '',
                    '{join_url}' => $meeting->join_url ?? '',
                    '{app_name}' => config('app.name')
                ];

                try {
                    $userLanguage = $member->lang ?? 'en';

                    $this->emailService->sendTemplateEmailWithLanguage(
                        templateName: 'Google Meeting Notification',
                        variables: $variables,
                        toEmail: $member->email,
                        toName: $member->name,
                        language: $userLanguage
                    );
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                    if (!str_contains($errorMessage, 'Too many emails per second') &&
                        !str_contains($errorMessage, '550 5.7.0') &&
                        !str_contains($errorMessage, 'rate limit')) {
                        session()->flash('email_error', 'Failed to send Google meeting notification email: ' . $errorMessage);
                    }
                }
            }
        }
    }
}