<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use Google\Client as GoogleClient;
use Google\Service\Calendar;

class GoogleMeetService
{
    private $client;
    private $baseUrl = 'https://www.googleapis.com/calendar/v3';

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Get Google Meet credentials for current user/workspace
     */
    private function getGoogleMeetCredentials()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return [
                'json_file' => null,
                'token' => null,
                'refresh_token' => null
            ];
        }

        // For workspace members, use owner's credentials
        $ownerId = $workspace->owner_id ?? $user->id;

        // Get the JSON file path
        $jsonFile = Setting::where('user_id', $ownerId)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'google_meet_json_file')
            ->value('value');

        // Get the token
        $token = Setting::where('user_id', $ownerId)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'google_meet_token')
            ->value('value');

        // Get the refresh token
        $refreshToken = Setting::where('user_id', $ownerId)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'google_meet_refresh_token')
            ->value('value');

        // Debug: Log the credentials for troubleshooting
        Log::info('Google Meet Credentials Check', [
            'user_id' => $user->id,
            'owner_id' => $ownerId,
            'workspace_id' => $workspace->id,
            'json_file' => $jsonFile,
            'has_token' => !empty($token),
            'has_refresh_token' => !empty($refreshToken)
        ]);

        return [
            'json_file' => $jsonFile,
            'token' => $token,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Get OAuth access token for Google API
     */
    private function getAccessToken()
    {
        $credentials = $this->getGoogleMeetCredentials();

        if (!$credentials['json_file'] || !$credentials['token']) {
            throw new \Exception('Google Meet credentials not configured');
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/public/' . $credentials['json_file']));

            // Check if token is expired
            $token = $credentials['token'];
            if (is_string($token)) {
                $token = html_entity_decode($token);
                $tokenData = json_decode($token, true);
            } else {
                $tokenData = $token;
            }
            $client->setAccessToken($tokenData);

            if ($client->isAccessTokenExpired()) {
                if ($credentials['refresh_token']) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($credentials['refresh_token']);

                    // Update the stored token
                    $user = auth()->user();
                    $workspace = $user->currentWorkspace;
                    $ownerId = $workspace->owner_id ?? $user->id;

                    Setting::updateOrCreate(
                        [
                            'user_id' => $ownerId,
                            'workspace_id' => $workspace->id,
                            'key' => 'google_meet_token'
                        ],
                        ['value' => json_encode($newToken)]
                    );

                    return $newToken['access_token'];
                } else {
                    throw new \Exception('Token expired and no refresh token available');
                }
            }

            return $tokenData['access_token'];
        } catch (\Exception $e) {
            Log::error('Google OAuth Error: ' . $e->getMessage());
            throw new \Exception('Failed to get Google access token: ' . $e->getMessage());
        }
    }

    /**
     * Create a new Google Meet meeting via Google Calendar
     */
    public function createMeeting($meetingData)
    {
        $credentials = $this->getGoogleMeetCredentials();

        // Check if credentials are available
        if (!$credentials['json_file'] || !$credentials['token']) {
            throw new \Exception('Google Meet credentials not configured. Please configure Google Meet settings first.');
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/public/' . $credentials['json_file']));

            // Set access token with validation
            $token = $credentials['token'];
            if (is_string($token)) {
                $token = html_entity_decode($token);
                $tokenData = json_decode($token, true);
            } else {
                $tokenData = $token;
            }
            if (!$tokenData || json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid Google token format. Please reconfigure Google Meet settings.');
            }
            $client->setAccessToken($tokenData);

            // Check if token is expired and refresh if needed
            if ($client->isAccessTokenExpired()) {
                if ($credentials['refresh_token']) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($credentials['refresh_token']);
                    $client->setAccessToken($newToken);

                    // Update stored token
                    $user = auth()->user();
                    $workspace = $user->currentWorkspace;
                    $ownerId = $workspace->owner_id ?? $user->id;

                    Setting::updateOrCreate(
                        [
                            'user_id' => $ownerId,
                            'workspace_id' => $workspace->id,
                            'key' => 'google_meet_token'
                        ],
                        ['value' => json_encode($newToken)]
                    );
                } else {
                    throw new \Exception('Google Calendar token expired - please reconfigure your settings.');
                }
            }

            $service = new Calendar($client);
            $event = new \Google_Service_Calendar_Event([
                'summary' => $meetingData['title'],
                'description' => $meetingData['description'] ?? '',
                'start' => [
                    'dateTime' => $meetingData['start_time'],
                    'timeZone' => $meetingData['timezone'] ?? 'UTC',
                ],
                'end' => [
                    'dateTime' => date('c', strtotime($meetingData['start_time'] . ' +' . $meetingData['duration'] . ' minutes')),
                    'timeZone' => $meetingData['timezone'] ?? 'UTC',
                ],
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => uniqid(),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet'
                        ]
                    ],
                ],
            ]);
            
            $createdEvent = $service->events->insert('primary', $event, ['conferenceDataVersion' => 1]);
            
            // Ensure we have a hangout link
            $hangoutLink = $createdEvent->getHangoutLink();
            if (!$hangoutLink) {
                throw new \Exception('Failed to create Google Meet link. Please check your Google Calendar configuration.');
            }
            
            return [
                'success' => true,
                'data' => [
                    'id' => $createdEvent->getId(),
                    'hangoutLink' => $hangoutLink,
                    'htmlLink' => $createdEvent->getHtmlLink()
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Google Meet Service Error: ' . $e->getMessage());
            throw new \Exception('Failed to create Google Meet: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing Google Meet meeting
     */
    public function updateMeeting($meetingId, $meetingData)
    {
        try {
            $credentials = $this->getGoogleMeetCredentials();

            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/public/' . $credentials['json_file']));

            // Set access token
            $tokenData = json_decode($credentials['token'], true);
            $client->setAccessToken($tokenData);

            // Check if token is expired and refresh if needed
            if ($client->isAccessTokenExpired()) {
                if ($credentials['refresh_token']) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($credentials['refresh_token']);
                    $client->setAccessToken($newToken);
                } else {
                    throw new \Exception('Token expired and no refresh token available');
                }
            }

            $service = new Calendar($client);

            $event = $service->events->get('primary', $meetingId);
            $event->setSummary($meetingData['title']);
            $event->setDescription($meetingData['description'] ?? '');

            $start = new \Google_Service_Calendar_EventDateTime();
            $start->setDateTime($meetingData['start_time']);
            $start->setTimeZone($meetingData['timezone'] ?? 'UTC');
            $event->setStart($start);

            $end = new \Google_Service_Calendar_EventDateTime();
            $end->setDateTime(date('c', strtotime($meetingData['start_time'] . ' +' . $meetingData['duration'] . ' minutes')));
            $end->setTimeZone($meetingData['timezone'] ?? 'UTC');
            $event->setEnd($end);

            $service->events->update('primary', $meetingId, $event);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Google Meet Update Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update Google Meet meeting: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a Google Meet meeting
     */
    public function deleteMeeting($meetingId)
    {
        try {
            $credentials = $this->getGoogleMeetCredentials();

            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/public/' . $credentials['json_file']));

            // Set access token
            $tokenData = json_decode($credentials['token'], true);
            $client->setAccessToken($tokenData);

            // Check if token is expired and refresh if needed
            if ($client->isAccessTokenExpired()) {
                if ($credentials['refresh_token']) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($credentials['refresh_token']);
                    $client->setAccessToken($newToken);
                } else {
                    throw new \Exception('Token expired and no refresh token available');
                }
            }

            $service = new Calendar($client);
            $service->events->delete('primary', $meetingId);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Google Meet Delete Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to delete Google Meet meeting: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get meeting details from Google Calendar
     */
    public function getMeeting($meetingId)
    {
        try {
            $credentials = $this->getGoogleMeetCredentials();

            $client = new GoogleClient();
            $client->setAuthConfig(storage_path('app/public/' . $credentials['json_file']));

            // Set access token
            $tokenData = json_decode($credentials['token'], true);
            $client->setAccessToken($tokenData);

            // Check if token is expired and refresh if needed
            if ($client->isAccessTokenExpired()) {
                if ($credentials['refresh_token']) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($credentials['refresh_token']);
                    $client->setAccessToken($newToken);
                } else {
                    throw new \Exception('Token expired and no refresh token available');
                }
            }

            $service = new Calendar($client);
            $event = $service->events->get('primary', $meetingId);

            return [
                'success' => true,
                'data' => $event
            ];
        } catch (\Exception $e) {
            Log::error('Google Meet Get Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to get Google Meet meeting: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if Google Meet credentials are configured
     */
    public function hasValidCredentials()
    {
        try {
            $user = auth()->user();
            $workspace = $user->currentWorkspace;

            if (!$workspace) {
                return false;
            }

            // For workspace members, check owner's credentials
            $ownerId = $workspace->owner_id ?? $user->id;

            // Check if JSON file exists
            $jsonFile = Setting::where('user_id', $ownerId)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'google_meet_json_file')
                ->value('value');

            // Check if test passed
            $isTestPassed = Setting::where('user_id', $ownerId)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'is_google_meeting_test')
                ->value('value');

            // Check if token exists
            $token = Setting::where('user_id', $ownerId)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'google_meet_token')
                ->value('value');

            return !empty($jsonFile) &&
                $isTestPassed === '1' &&
                !empty($token) &&
                file_exists(storage_path('app/public/' . $jsonFile));
        } catch (\Exception $e) {
            Log::error('Error checking Google Meet credentials: ' . $e->getMessage());
            return false;
        }
    }
}