<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Workspace;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Log;

/**
 * Service-account based Google Calendar integration, matching the existing
 * frontend at resources/js/pages/settings/components/google-calendar-settings.tsx
 * (upload a service-account JSON, enable, set a Calendar ID, "Test Sync" -
 * no interactive OAuth consent screen). This is a different auth model than
 * GoogleMeetService's user-consent OAuth flow: a service account authenticates
 * server-to-server via its JSON key once its target calendar has been shared
 * with the service account's client_email in Google Calendar's sharing settings.
 * google/apiclient is already a vendored dependency (confirmed in use by
 * GoogleMeetService) - no new package is needed.
 */
class GoogleCalendarService
{
    public function __construct() {}

    private function resolveOwnerId($userId, $workspaceId): ?int
    {
        if ($workspaceId) {
            $workspace = Workspace::find($workspaceId);
            if ($workspace) {
                return $workspace->owner_id ?? $userId;
            }
        }

        return $userId;
    }

    private function getConfig($userId, $workspaceId = null): array
    {
        $ownerId = $this->resolveOwnerId($userId, $workspaceId);

        if (!$ownerId || !$workspaceId) {
            return ['enabled' => false, 'json_file' => null, 'calendar_id' => 'primary'];
        }

        $keys = Setting::where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->whereIn('key', ['googleCalendarEnabled', 'googleCalendarJsonFile', 'googleCalendarId'])
            ->pluck('value', 'key');

        return [
            'enabled' => $keys->get('googleCalendarEnabled') === '1',
            'json_file' => $keys->get('googleCalendarJsonFile'),
            'calendar_id' => $keys->get('googleCalendarId') ?: 'primary',
        ];
    }

    public function isEnabled($userId, $workspaceId = null): bool
    {
        $config = $this->getConfig($userId, $workspaceId);

        return $config['enabled']
            && !empty($config['json_file'])
            && file_exists(storage_path('app/public/' . $config['json_file']));
    }

    public function isAuthorized($userId, $workspaceId = null): bool
    {
        $ownerId = $this->resolveOwnerId($userId, $workspaceId);
        $testPassed = Setting::where('user_id', $ownerId)
            ->where('workspace_id', $workspaceId)
            ->where('key', 'is_googlecalendar_sync')
            ->value('value');

        return $this->isEnabled($userId, $workspaceId) && $testPassed === '1';
    }

    /**
     * Throws if not configured - callers that only need a yes/no check
     * should use isEnabled()/isAuthorized() instead.
     */
    private function getClient($userId, $workspaceId = null): GoogleClient
    {
        $config = $this->getConfig($userId, $workspaceId);

        if (!$this->isEnabled($userId, $workspaceId)) {
            throw new \Exception('Google Calendar is not configured. Please upload a service account JSON file in settings.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig(storage_path('app/public/' . $config['json_file']));
        $client->addScope(Calendar::CALENDAR);

        return $client;
    }

    private function calendarId($userId, $workspaceId): string
    {
        return $this->getConfig($userId, $workspaceId)['calendar_id'];
    }

    /**
     * Verifies the service account can actually read the configured calendar
     * (i.e. the calendar has been shared with the service account's email).
     * Used by the "Test Sync" button - a config that merely has a file
     * uploaded doesn't guarantee the calendar was ever shared with it.
     */
    public function testConnection($userId, $workspaceId, $calendarId = null): array
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);
            $service->calendars->get($calendarId ?: $this->calendarId($userId, $workspaceId));

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Google Calendar test connection failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $this->friendlyError($e)];
        }
    }

    private function friendlyError(\Exception $e): string
    {
        if (str_contains($e->getMessage(), '404')) {
            return __('Calendar not found. Check the Calendar ID and make sure it is shared with the service account.');
        }
        if (str_contains($e->getMessage(), '403')) {
            return __('Access denied. Share this calendar with the service account\'s email (found in the JSON file) as an editor.');
        }

        return __('Failed to connect to Google Calendar: :error', ['error' => $e->getMessage()]);
    }

    public function getEvents($userId, $maxResults = 100, $workspaceId = null): array
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);

            $results = $service->events->listEvents($this->calendarId($userId, $workspaceId), [
                'maxResults' => $maxResults,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => date('c'),
            ]);

            return array_map(function ($event) {
                return [
                    'id' => $event->getId(),
                    'title' => $event->getSummary(),
                    'description' => $event->getDescription(),
                    'start' => $event->getStart()?->getDateTime() ?? $event->getStart()?->getDate(),
                    'end' => $event->getEnd()?->getDateTime() ?? $event->getEnd()?->getDate(),
                    'htmlLink' => $event->getHtmlLink(),
                ];
            }, $results->getItems());
        } catch (\Exception $e) {
            Log::error('Google Calendar getEvents error: ' . $e->getMessage());
            return [];
        }
    }

    private function buildEventPayload($item): \Google_Service_Calendar_Event
    {
        $title = $item->title ?? $item->name ?? $item->subject ?? 'Untitled event';
        $description = $item->description ?? '';
        $startTime = $item->start_time ?? $item->date ?? now()->toIso8601String();
        $timezone = $item->timezone ?? config('app.timezone', 'UTC');

        if (!empty($item->end_time)) {
            $endTime = $item->end_time;
        } elseif (!empty($item->duration)) {
            $endTime = date('c', strtotime($startTime . ' +' . $item->duration . ' minutes'));
        } else {
            $endTime = date('c', strtotime($startTime . ' +30 minutes'));
        }

        return new \Google_Service_Calendar_Event([
            'summary' => $title,
            'description' => $description,
            'start' => ['dateTime' => $startTime, 'timeZone' => $timezone],
            'end' => ['dateTime' => $endTime, 'timeZone' => $timezone],
        ]);
    }

    public function createEvent($item, $userId, $workspaceId = null): ?string
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);

            $created = $service->events->insert($this->calendarId($userId, $workspaceId), $this->buildEventPayload($item));

            return $created->getId();
        } catch (\Exception $e) {
            Log::error('Google Calendar createEvent error: ' . $e->getMessage());
            return null;
        }
    }

    public function updateEvent($eventId, $item, $userId, $workspaceId = null): bool
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);

            $service->events->update($this->calendarId($userId, $workspaceId), $eventId, $this->buildEventPayload($item));

            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar updateEvent error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteEvent($eventId, $userId, $workspaceId = null): bool
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);

            $service->events->delete($this->calendarId($userId, $workspaceId), $eventId);

            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar deleteEvent error: ' . $e->getMessage());
            return false;
        }
    }

    public function createMeetingEvent($meeting, $userId, $workspaceId = null): ?string
    {
        return $this->createEvent($meeting, $userId, $workspaceId);
    }

    public function updateMeetingEvent($eventId, $meeting, $userId, $workspaceId = null): bool
    {
        return $this->updateEvent($eventId, $meeting, $userId, $workspaceId);
    }

    public function createGoogleMeetingEvent($meeting, $userId, $workspaceId = null): ?string
    {
        try {
            $client = $this->getClient($userId, $workspaceId);
            $service = new Calendar($client);

            $event = $this->buildEventPayload($meeting);
            $event->setConferenceData([
                'createRequest' => [
                    'requestId' => uniqid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ]);

            $created = $service->events->insert($this->calendarId($userId, $workspaceId), $event, ['conferenceDataVersion' => 1]);

            return $created->getId();
        } catch (\Exception $e) {
            Log::error('Google Calendar createGoogleMeetingEvent error: ' . $e->getMessage());
            return null;
        }
    }
}
