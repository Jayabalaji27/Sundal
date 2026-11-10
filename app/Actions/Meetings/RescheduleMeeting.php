<?php

namespace App\Actions\Meetings;

use App\Actions\ActionException;
use App\Models\GoogleMeeting;
use App\Models\User;
use App\Models\ZoomMeeting;
use App\Services\GoogleCalendarService;
use App\Services\GoogleMeetService;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves or renames a Zoom or Google Meet meeting for the AI assistant: the
 * provider's meeting first (as the Meetings pages' Edit does), then Sundal.
 * A meeting kept only in Sundal (Zoom was not connected) just changes here.
 * Permission and "who may change it" checks stay with the caller.
 *
 * @param  array{title?: string, start_time?: Carbon, duration?: int}  $changes
 */
class RescheduleMeeting
{
    public function __construct(
        private readonly ZoomService $zoom,
        private readonly GoogleMeetService $googleMeet,
        private readonly GoogleCalendarService $calendar,
    ) {}

    public function handle(User $actor, ZoomMeeting|GoogleMeeting $meeting, array $changes): Model
    {
        $title = $changes['title'] ?? $meeting->title;
        $start = $changes['start_time'] ?? Carbon::parse($meeting->start_time);
        $duration = (int) ($changes['duration'] ?? $meeting->duration ?: 60);
        $timezone = $meeting instanceof ZoomMeeting ? ($meeting->timezone ?: config('app.timezone', 'UTC')) : $start->getTimezone()->getName();

        $data = [
            'title' => $title,
            'description' => $meeting->description,
            'start_time' => $start->toISOString(),
            'duration' => $duration,
            'timezone' => $timezone,
            'password' => $meeting instanceof ZoomMeeting ? $meeting->password : null,
        ];

        if ($meeting instanceof ZoomMeeting) {
            if ($meeting->zoom_meeting_id && !str_starts_with((string) $meeting->zoom_meeting_id, 'local_')) {
                $response = $this->zoom->updateMeeting($meeting->zoom_meeting_id, $data);
                if (!$response['success']) {
                    throw new ActionException(__('Zoom did not accept the change: :error', ['error' => $response['error'] ?? '']));
                }
            }
        } elseif ($meeting->google_calendar_event_id) {
            $response = $this->googleMeet->updateMeeting($meeting->google_calendar_event_id, $data);
            if (!$response['success']) {
                throw new ActionException(__('Google did not accept the change: :error', ['error' => $response['error'] ?? '']));
            }
        }

        $meeting->update([
            'title' => $title,
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes($duration),
            'duration' => $duration,
        ]);

        // A Zoom meeting also shown in Google Calendar: move that event too.
        if ($meeting instanceof ZoomMeeting && $meeting->google_calendar_event_id) {
            try {
                $this->calendar->updateEvent($meeting->google_calendar_event_id, $meeting, $actor->id, $actor->current_workspace_id);
            } catch (\Throwable $e) {
                \Log::error('Failed to update Google Calendar event', ['meeting_id' => $meeting->id, 'error' => $e->getMessage()]);
            }
        }

        return $meeting;
    }
}
