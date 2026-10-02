<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;

/**
 * Backend for resources/js/pages/settings/components/google-calendar-settings.tsx,
 * which already existed and expects a service-account JSON (no interactive OAuth
 * redirect/consent - unlike GoogleMeetSettingsController's client-secret + user
 * consent flow). A service account authenticates server-to-server via the JSON
 * key alone once its calendar is shared with the service account's email, so
 * there is no redirect/callback route here, just upload + enable + a
 * "Test Sync" connectivity check.
 */
class GoogleCalendarSettingsController extends Controller
{
    public function update(Request $request)
    {
        $enabled = $request->boolean('googleCalendarEnabled');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $hasExistingFile = (bool) Setting::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'googleCalendarJsonFile')
            ->value('value');

        $request->validate([
            'googleCalendarId' => 'nullable|string|max:255',
            'googleCalendarJson' => ($enabled && !$hasExistingFile) ? 'required|file|mimes:json' : 'nullable|file|mimes:json',
        ]);

        $settings = [
            'googleCalendarEnabled' => $enabled ? '1' : '0',
            'googleCalendarId' => $request->input('googleCalendarId', ''),
        ];

        if ($request->hasFile('googleCalendarJson')) {
            $oldFile = Setting::where('user_id', $user->id)
                ->where('workspace_id', $workspace->id)
                ->where('key', 'googleCalendarJsonFile')
                ->value('value');

            if ($oldFile && \Storage::disk('public')->exists($oldFile)) {
                \Storage::disk('public')->delete($oldFile);
            }

            $file = $request->file('googleCalendarJson');
            $fileName = time() . '_google_calendar_credentials.json';
            $settings['googleCalendarJsonFile'] = $file->storeAs('google_calendar', $fileName, 'public');
            // A new credentials file invalidates any prior successful sync check.
            $settings['is_googlecalendar_sync'] = '0';
        }

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['user_id' => $user->id, 'workspace_id' => $workspace->id, 'key' => $key],
                ['value' => $value]
            );
        }

        return back()->withSuccess(__('Google Calendar settings updated successfully!'));
    }

    public function sync(GoogleCalendarService $calendarService)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        if (!$calendarService->isEnabled($user->id, $workspace->id)) {
            return back()->withErrors(['error' => __('Please upload a Google Calendar service account JSON file first.')]);
        }

        $calendarId = getSetting('googleCalendarId', '', $user->id, $workspace->id) ?: 'primary';

        $result = $calendarService->testConnection($user->id, $workspace->id, $calendarId);

        Setting::updateOrCreate(
            ['user_id' => $user->id, 'workspace_id' => $workspace->id, 'key' => 'is_googlecalendar_sync'],
            ['value' => $result['success'] ? '1' : '0']
        );

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->withSuccess(__('Google Calendar connection verified successfully.'));
    }
}
