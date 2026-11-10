<?php

namespace App\Actions\Meetings;

use App\Actions\ActionException;
use App\Models\GoogleMeeting;
use App\Models\User;
use App\Models\ZoomMeeting;
use App\Services\GoogleCalendarService;
use App\Services\GoogleMeetService;
use App\Services\ZoomService;

/**
 * Cancels a Zoom or Google Meet meeting for the AI assistant, as Delete on
 * the Meetings pages does: removed from the provider (and Google Calendar),
 * then from Sundal. Permission and "who may change it" checks stay with the
 * caller.
 */
class CancelMeeting
{
    public function __construct(
        private readonly ZoomService $zoom,
        private readonly GoogleMeetService $googleMeet,
        private readonly GoogleCalendarService $calendar,
    ) {}

    public function handle(User $actor, ZoomMeeting|GoogleMeeting $meeting): void
    {
        if ($meeting instanceof ZoomMeeting) {
            if ($meeting->google_calendar_event_id) {
                try {
                    $this->calendar->deleteEvent($meeting->google_calendar_event_id, $actor->id, $actor->current_workspace_id);
                } catch (\Throwable $e) {
                    \Log::error('Failed to delete Google Calendar event', ['meeting_id' => $meeting->id, 'error' => $e->getMessage()]);
                }
            }
            // Only real Zoom meetings live at Zoom (not the ones kept in Sundal only).
            if ($meeting->zoom_meeting_id && !str_starts_with((string) $meeting->zoom_meeting_id, 'local_')) {
                $response = $this->zoom->deleteMeeting($meeting->zoom_meeting_id);
                if (!$response['success']) {
                    throw new ActionException(__('Zoom did not cancel the meeting: :error', ['error' => $response['error'] ?? '']));
                }
            }
        } elseif ($meeting->google_calendar_event_id) {
            try {
                $this->googleMeet->deleteMeeting($meeting->google_calendar_event_id);
            } catch (\Throwable $e) {
                \Log::error('Failed to delete Google Calendar event', ['meeting_id' => $meeting->id, 'error' => $e->getMessage()]);
            }
        }

        $meeting->delete();
    }
}
