<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

class ZoomService
{
    private $client;
    private $baseUrl = 'https://api.zoom.us/v2';

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Get Zoom credentials for current user/workspace
     */
    private function getZoomCredentials()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return [
                'account_id' => null,
                'client_id' => null,
                'client_secret' => null
            ];
        }

        // First, try to get workspace-level settings (configured by admin/owner)
        $accountId = Setting::where('workspace_id', $workspace->id)
            ->where('key', 'zoom_account_id')
            ->whereHas('user', function($query) {
                $query->whereHas('roles', function($roleQuery) {
                    $roleQuery->whereIn('name', ['company', 'owner']);
                });
            })
            ->value('value');

        $clientId = Setting::where('workspace_id', $workspace->id)
            ->where('key', 'zoom_client_id')
            ->whereHas('user', function($query) {
                $query->whereHas('roles', function($roleQuery) {
                    $roleQuery->whereIn('name', ['company', 'owner']);
                });
            })
            ->value('value');

        $clientSecret = Setting::where('workspace_id', $workspace->id)
            ->where('key', 'zoom_client_secret')
            ->whereHas('user', function($query) {
                $query->whereHas('roles', function($roleQuery) {
                    $roleQuery->whereIn('name', ['company', 'owner']);
                });
            })
            ->value('value');

        // If no workspace-level settings found, fall back to current user's settings
        if (!$accountId || !$clientId || !$clientSecret) {
            $accountId = $accountId ?: Setting::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'zoom_account_id')
                ->value('value');

            $clientId = $clientId ?: Setting::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'zoom_client_id')
                ->value('value');

            $clientSecret = $clientSecret ?: Setting::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'zoom_client_secret')
                ->value('value');
        }

        return [
            'account_id' => $accountId,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];
    }

    /**
     * Get OAuth access token for Zoom API
     */
    private function getAccessToken()
    {
        $credentials = $this->getZoomCredentials();

        if (!$credentials['account_id'] || !$credentials['client_id'] || !$credentials['client_secret']) {
            throw new \Exception('Zoom credentials not configured');
        }

        try {
            $response = $this->client->post('https://zoom.us/oauth/token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($credentials['client_id'] . ':' . $credentials['client_secret']),
                ],
                'form_params' => [
                    'grant_type' => 'account_credentials',
                    'account_id' => $credentials['account_id']
                ],
            ]);

            $tokenData = json_decode($response->getBody(), true);
            return $tokenData['access_token'];
        } catch (RequestException $e) {
            Log::error('Zoom OAuth Error: ' . $e->getMessage());
            throw new \Exception('Failed to get Zoom access token');
        }
    }

    /**
     * Create a new Zoom meeting
     */
    public function createMeeting($meetingData)
    {
        try {
            $credentials = $this->getZoomCredentials();

            // Server-to-Server OAuth flow
            $tokenResponse = $this->client->post('https://zoom.us/oauth/token', [
                'form_params' => [
                    'grant_type' => 'account_credentials',
                    'account_id' => $credentials['account_id'],
                ],
                'auth' => [$credentials['client_id'], $credentials['client_secret']],
            ]);

            $tokenData = json_decode($tokenResponse->getBody(), true);

            $accessToken = $tokenData['access_token'];
            $response = $this->client->post($this->baseUrl . '/users/me/meetings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'topic' => $meetingData['title'],
                    'type' => 2,
                    'start_time' => $meetingData['start_time'],
                    'duration' => $meetingData['duration'],
                    'timezone' => $meetingData['timezone'] ?? 'UTC',
                    'agenda' => $meetingData['description'] ?? '',
                    'password' => $meetingData['password'] ?? null,
                    'settings' => [
                        'host_video' => true,
                        'participant_video' => true,
                        'join_before_host' => false,
                        'mute_upon_entry' => true,
                        'waiting_room' => true,
                        'auto_recording' => 'none'
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (RequestException $e) {
            Log::error('Zoom Create Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to create Zoom meeting: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Zoom Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    /**
     * Update an existing Zoom meeting
     */
    public function updateMeeting($meetingId, $meetingData)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->patch($this->baseUrl . '/meetings/' . $meetingId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'topic' => $meetingData['title'],
                    'start_time' => $meetingData['start_time'],
                    'duration' => $meetingData['duration'],
                    'timezone' => $meetingData['timezone'] ?? 'UTC',
                    'agenda' => $meetingData['description'] ?? '',
                    'password' => $meetingData['password'] ?? null,
                ]
            ]);

            return ['success' => true];
        } catch (RequestException $e) {
            Log::error('Zoom Update Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to update Zoom meeting: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Zoom Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a Zoom meeting
     */
    public function deleteMeeting($meetingId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $this->client->delete($this->baseUrl . '/meetings/' . $meetingId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]);

            return ['success' => true];
        } catch (RequestException $e) {
            Log::error('Zoom Delete Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to delete Zoom meeting: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Zoom Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get meeting details from Zoom
     */
    public function getMeeting($meetingId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = $this->client->get($this->baseUrl . '/meetings/' . $meetingId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (RequestException $e) {
            Log::error('Zoom Get Meeting Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to get Zoom meeting: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Zoom Service Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if Zoom credentials are configured
     */
    public function hasValidCredentials()
    {
        try {
            $credentials = $this->getZoomCredentials();
            return !empty($credentials['account_id']) &&
                !empty($credentials['client_id']) &&
                !empty($credentials['client_secret']);
        } catch (\Exception $e) {
            return false;
        }
    }

    // Google Calendar Integration Methods (COMMENTED OUT - NOT USED RIGHT NOW)
    /*
    public function syncMeetingToGoogleCalendar($meeting)
    {
        // TODO: Implement Google Calendar sync
        // This would create a Google Calendar event for the Zoom meeting
        // Include Zoom meeting details in the calendar event description
        // Store the Google Calendar event ID in the meeting record

        return [
            'success' => true,
            'google_event_id' => 'sample_event_id'
        ];
    }

    public function updateGoogleCalendarEvent($meeting)
    {
        // TODO: Implement Google Calendar update
        // Update the existing Google Calendar event with new meeting details

        return ['success' => true];
    }

    public function deleteFromGoogleCalendar($googleEventId)
    {
        // TODO: Implement Google Calendar deletion
        // Remove the event from Google Calendar

        return ['success' => true];
    }
    */
}