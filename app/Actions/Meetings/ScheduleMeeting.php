<?php

namespace App\Actions\Meetings;

use App\Events\GoogleMeetingCreated;
use App\Events\ZoomMeetingCreated;
use App\Models\GoogleMeeting;
use App\Models\User;
use App\Models\ZoomMeeting;
use App\Services\Ai\AiActionContext;
use App\Services\GoogleMeetService;
use App\Services\IntegrationLog;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Schedules a Zoom or Google Meet meeting: creates it with the provider (which
 * gives the join link), stores it, invites the people and sends the invitation
 * emails. Shared by the Meetings pages and the AI assistant. Expects validated
 * data; permission and project checks stay with the caller.
 *
 * @param  array{title: string, description?: ?string, start_time: Carbon, duration: int, timezone?: string,
 *               project_id: int, member_ids: int[], password?: ?string, is_googlecalendar_sync?: bool}  $data
 */
class ScheduleMeeting
{
    public function __construct(
        private readonly ZoomService $zoom,
        private readonly GoogleMeetService $googleMeet,
    ) {}

    public function handle(User $actor, string $provider, array $data): Model
    {
        $started = hrtime(true);
        $context = [
            'provider' => $provider,
            'project_id' => $data['project_id'] ?? null,
            'source' => AiActionContext::metadata() ? 'ai_assistant' : 'screen',
        ];

        try {
            $meeting = $provider === 'zoom' ? $this->zoom($actor, $data) : $this->google($actor, $data);
        } catch (\Throwable $e) {
            IntegrationLog::failed('meetings', 'schedule', $e, $started, $context);

            throw $e;
        }

        if (!empty($data['member_ids'])) {
            $meeting->members()->attach($data['member_ids']);
        }

        if (!config('app.is_demo', true)) {
            event($provider === 'zoom' ? new ZoomMeetingCreated($meeting) : new GoogleMeetingCreated($meeting));
        }

        // join_link false: kept in Sundal only (Zoom not connected, or its API failed; see the zoom.* line).
        IntegrationLog::ok('meetings', 'schedule', $started, $context + [
            'meeting_id' => $meeting->id,
            'attendees' => count($data['member_ids'] ?? []),
            'join_link' => (bool) $meeting->join_url,
        ]);

        return $meeting;
    }

    /** Zoom: without a working Zoom connection the meeting is kept, without a join link. */
    private function zoom(User $actor, array $data): ZoomMeeting
    {
        $start = $data['start_time'];
        $timezone = $data['timezone'] ?? config('app.timezone', 'UTC');
        $meetingId = 'local_' . time();
        $startUrl = $joinUrl = null;
        $password = $data['password'] ?? null;

        if ($this->zoom->hasValidCredentials()) {
            $response = $this->zoom->createMeeting([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'start_time' => $start->toISOString(),
                'duration' => $data['duration'],
                'timezone' => $timezone,
                'password' => $password,
            ]);
            if ($response['success']) {
                $meetingId = $response['data']['id'];
                $startUrl = $response['data']['start_url'];
                $joinUrl = $response['data']['join_url'];
                $password = $response['data']['password'] ?? $password;
            }
        }

        return ZoomMeeting::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'workspace_id' => $actor->current_workspace_id,
            'zoom_meeting_id' => $meetingId,
            'project_id' => $data['project_id'],
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes($data['duration']),
            'duration' => $data['duration'],
            'timezone' => $timezone,
            'start_url' => $startUrl,
            'password' => $password,
            'join_url' => $joinUrl,
            'status' => 'scheduled',
            'user_id' => $actor->id,
            'is_googlecalendar_sync' => $data['is_googlecalendar_sync'] ?? false,
        ]);
    }

    /** Google Meet: the meeting is an event in the owner's Google Calendar; a failure is thrown. */
    private function google(User $actor, array $data): GoogleMeeting
    {
        $start = $data['start_time'];
        $response = $this->googleMeet->createMeeting([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $start->toISOString(),
            'duration' => $data['duration'],
            'timezone' => $start->getTimezone()->getName(),
        ]);

        return GoogleMeeting::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'workspace_id' => $actor->current_workspace_id,
            'project_id' => $data['project_id'],
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes($data['duration']),
            'duration' => $data['duration'],
            'join_url' => $response['data']['hangoutLink'],
            'start_url' => $response['data']['hangoutLink'],
            'status' => 'scheduled',
            'user_id' => $actor->id,
            'google_calendar_event_id' => $response['data']['id'],
        ]);
    }
}
