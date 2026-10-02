<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ZoomSettingsController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'zoom_account_id' => 'required|string',
            'zoom_client_id' => 'required|string',
            'zoom_client_secret' => 'required|string',
        ]);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $settings = [
            'zoom_account_id' => $request->zoom_account_id,
            'zoom_client_id' => $request->zoom_client_id,
            'zoom_client_secret' => $request->zoom_client_secret,
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

        // Reset validation status when credentials change
        Setting::updateOrCreate(
            [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'key' => 'is_zoom_meeting_test'
            ],
            ['value' => '0']
        );

        return back()->withSuccess(__('Zoom settings updated successfully!'));
    }

    public function test(Request $request)
    {
        $request->validate([
            'zoom_account_id' => 'required|string',
            'zoom_client_id' => 'required|string',
            'zoom_client_secret' => 'required|string',
        ]);

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found.')]);
        }

        try {
            // Correct Zoom Authentication
            $basicToken = base64_encode($request->zoom_client_id . ':' . $request->zoom_client_secret);

            $tokenResponse = Http::withHeaders([
                'Authorization' => 'Basic ' . $basicToken,
            ])->asForm()->post('https://zoom.us/oauth/token', [
                        'grant_type' => 'account_credentials',
                        'account_id' => $request->zoom_account_id,
                    ]);


            if (!$tokenResponse->successful()) {
                throw new \Exception($tokenResponse->json()['error'] ?? 'Authentication failed');
            }

            $accessToken = $tokenResponse->json('access_token');
            // Test Zoom API
            $userResponse = Http::withToken($accessToken)
                ->get('https://api.zoom.us/v2/users');


            if (!$userResponse->successful()) {
                throw new \Exception('Failed to validate credentials: ' . json_encode($userResponse->json()));
            }


            Setting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'key' => 'is_zoom_meeting_test'
                ],
                ['value' => '1']
            );

            return back()->withSuccess(__('Zoom credentials validated successfully!'));

        } catch (\Exception $e) {

            Setting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'key' => 'is_zoom_meeting_test'
                ],
                ['value' => '0']
            );

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

}