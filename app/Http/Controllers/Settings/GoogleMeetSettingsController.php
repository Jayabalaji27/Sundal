<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Calendar;
use Google_Service_Calendar;

class GoogleMeetSettingsController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'google_meet_json_file' => 'required|file|mimes:json',
        ]);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        if ($request->hasFile('google_meet_json_file')) {
            // Delete old file if exists
            $oldFile = Setting::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'google_meet_json_file')
                ->value('value');
            
            if ($oldFile && \Storage::disk('public')->exists($oldFile)) {
                \Storage::disk('public')->delete($oldFile);
            }
            
            $file = $request->file('google_meet_json_file');
            $fileName = time() . '_google_meet_credentials.json';
            $filePath = $file->storeAs('google_meet', $fileName, 'public');

            $settings = [
                'google_meet_json_file' => $filePath,
                'google_meet_token' => '',
                'google_meet_refresh_token' => '',
                'is_google_meeting_test' => '0',
            ];

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'workspace_id' => $workspace->id,
                        'key' => $key
                    ],
                    ['value' => $value]
                );
            }
        }

        return back()->withSuccess(__('Google Meet settings updated successfully!'));
    }

    // Authenticate with google
    public function redirectToGoogle()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $jsonFile = Setting::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'google_meet_json_file')
            ->value('value');

        if (!$jsonFile || !file_exists(storage_path('app/public/' . $jsonFile))) {
            return back()->withErrors(['error' => __('Please upload Google Meet credentials file first.')]);
        }

        // Set test status to 0 when starting sync
        Setting::updateOrCreate(
            [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'key' => 'is_google_meeting_test'
            ],
            ['value' => '0']
        );

        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/public/' . $jsonFile));
            $client->setRedirectUri(route('settings.google-meet.handleGoogleCallback'));
            $client->addScope('https://www.googleapis.com/auth/drive');
            $client->addScope(Google_Service_Calendar::CALENDAR);
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            return redirect($client->createAuthUrl());
        } catch (\Exception $e) {
            return redirect('/')->with('error', __($e->getMessage()));
        }
    }

    // Authenticate with google callback function
    public function handleGoogleCallback(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $jsonFile = Setting::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'google_meet_json_file')
            ->value('value');

        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/public/' . $jsonFile));
            $client->setRedirectUri(route('settings.google-meet.handleGoogleCallback'));

            $token = $client->fetchAccessTokenWithAuthCode($request->code);

            $settings = [
                'google_meet_token' => json_encode($token)
            ];

            if (isset($token['refresh_token'])) {
                $settings['google_meet_refresh_token'] = $token['refresh_token'];
            }

            // Mark as test passed
            $settings['is_google_meeting_test'] = '1';

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'workspace_id' => $workspace->id,
                        'key' => $key
                    ],
                    ['value' => $value]
                );
            }

            return redirect()->route('settings')->withSuccess(__('Authentication Successful'));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}