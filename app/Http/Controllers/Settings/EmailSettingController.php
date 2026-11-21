<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\Workspace;

class EmailSettingController extends Controller
{
    /**
     * Get email settings for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmailSettings()
    {
        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $settings = [
            'provider' => getSetting('email_provider', 'smtp', $user->id, $workspaceId),
            'driver' => getSetting('email_driver', 'smtp', $user->id, $workspaceId),
            'host' => getSetting('email_host', 'smtp.example.com', $user->id, $workspaceId),
            'port' => getSetting('email_port', '587', $user->id, $workspaceId),
            'username' => getSetting('email_username', 'user@example.com', $user->id, $workspaceId),
            'password' => getSetting('email_password', '', $user->id, $workspaceId),
            'encryption' => getSetting('email_encryption', 'tls', $user->id, $workspaceId),
            'fromAddress' => getSetting('email_from_address', 'noreply@example.com', $user->id, $workspaceId),
            'fromName' => getSetting('email_from_name', config('app.name'), $user->id, $workspaceId)
        ];

        // Mask password if it exists
        if (!empty($settings['password'])) {
            $settings['password'] = '••••••••••••';
        }

        return response()->json($settings);
    }

    /**
     * Update email settings for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    private const KEYS = [
        'email_provider', 'email_driver', 'email_host', 'email_port', 'email_username',
        'email_password', 'email_encryption', 'email_from_address', 'email_from_name',
    ];

    private const MASK = '••••••••••••';

    public function updateEmailSettings(Request $request)
    {
        $user = Auth::user();
        $workspaceId = null;

        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
            // Emails are sent with the workspace OWNER's settings (MailConfigService), so
            // only the owner's save would take effect.
            $workspace = Workspace::find($workspaceId);
            if (!$workspace || (int) $workspace->owner_id !== (int) $user->id) {
                abort(403, __('Only the workspace owner can change its email settings.'));
            }

            // "Disconnect": remove the company's mail server. In SaaS mode its emails then
            // stop until a server is set up again (no platform fallback). Deleted one by one
            // so the settings cache is cleared (Setting's deleted event).
            if ($request->boolean('disconnect')) {
                Setting::where('user_id', $user->id)->where('workspace_id', $workspaceId)
                    ->whereIn('key', self::KEYS)->get()->each->delete();

                return redirect()->back()->with('success', __('Mail server disconnected. Your company\'s emails won\'t be sent until you set one up again.'));
            }
        }

        // In SaaS mode a company's own server must be public SMTP on a mail port, so it
        // can't be used to reach the platform's internal network or run local programs.
        $restricted = isSaasMode() && $user->type !== 'superadmin';
        $hasSavedPassword = (string) getSetting('email_password', '', $user->id, $workspaceId) !== '';

        $validated = $request->validate([
            'provider' => 'required|string',
            'driver' => $restricted ? 'required|in:smtp' : 'required|string',
            'host' => ['required', 'string', function ($attribute, $value, $fail) use ($restricted) {
                if ($restricted && \App\Services\MailConfigService::isPrivateHost($value)) {
                    $fail(__('Use your mail provider\'s public server (e.g. smtp.gmail.com), not an internal address.'));
                }
            }],
            'port' => $restricted ? 'required|in:25,465,587,2525' : 'required|string',
            'username' => 'required|string',
            'password' => [($restricted && !$hasSavedPassword) ? 'required' : 'nullable', 'string', function ($attribute, $value, $fail) use ($restricted, $hasSavedPassword) {
                if ($restricted && !$hasSavedPassword && $value === self::MASK) {
                    $fail(__('Enter the password for this mail account.'));
                }
            }],
            'encryption' => 'required|string',
            'fromAddress' => 'required|email',
            'fromName' => 'required|string',
        ], [
            'port.in' => __('Use a standard mail port: 587 (TLS), 465 (SSL), 25 or 2525.'),
        ]);

        updateSetting('email_provider', $validated['provider'], $user->id, $workspaceId);
        updateSetting('email_driver', $validated['driver'], $user->id, $workspaceId);
        updateSetting('email_host', $validated['host'], $user->id, $workspaceId);
        updateSetting('email_port', $validated['port'], $user->id, $workspaceId);
        updateSetting('email_username', $validated['username'], $user->id, $workspaceId);
        
        // Only update password if provided and not masked
        if (!empty($validated['password']) && $validated['password'] !== self::MASK) {
            updateSetting('email_password', $validated['password'], $user->id, $workspaceId);
        }
        
        updateSetting('email_encryption', $validated['encryption'], $user->id, $workspaceId);
        updateSetting('email_from_address', $validated['fromAddress'], $user->id, $workspaceId);
        updateSetting('email_from_name', $validated['fromName'], $user->id, $workspaceId);

        return redirect()->back()->with('success', __('Email settings updated successfully'));
    }

    /**
     * Send a test email.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function sendTestEmail(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
            ]
        );

        // Failures go back as validation errors (not an 'error' flash) so the page's
        // onError handler sees them; FlashMessages clears flashes before onSuccess runs.
        if ($validator->fails()) {
            return redirect()->back()->withErrors(['email' => $validator->errors()->first()]);
        }

        $user = auth()->user();
        $workspaceId = null;

        if ($user->type === 'company') {
            // Test exactly what the company's emails will use (MailConfigService): its own
            // server (SaaS mode has no platform fallback for companies).
            $workspace = Workspace::find($user->current_workspace_id);
            $ownerId = $workspace?->owner_id ?? $user->id;
            if (!\App\Services\MailConfigService::isEmailConfigured($ownerId, $workspace?->id)) {
                return redirect()->back()->withErrors(['error' => __('Your mail server isn\'t set up yet. Fill in the details, click Save Changes, then send the test.')]);
            }

            try {
                \App\Services\MailConfigService::setDynamicConfig($ownerId, $workspace?->id);
                Mail::to($request->email)->send(new TestMail());
            } catch (\Exception $e) {
                return redirect()->back()->withErrors(['error' => __('Failed to send test email: :message', ['message' => $e->getMessage()])]);
            }

            return redirect()->back()->with('success', __('Test email sent to :email through your mail server.', ['email' => $request->email]));
        }

        $settings = [
            'provider' => getSetting('email_provider', 'smtp', $user->id, $workspaceId),
            'driver' => getSetting('email_driver', 'smtp', $user->id, $workspaceId),
            'host' => getSetting('email_host', 'smtp.example.com', $user->id, $workspaceId),
            'port' => getSetting('email_port', '587', $user->id, $workspaceId),
            'username' => getSetting('email_username', 'user@example.com', $user->id, $workspaceId),
            'encryption' => getSetting('email_encryption', 'tls', $user->id, $workspaceId),
            'fromAddress' => getSetting('email_from_address', 'noreply@example.com', $user->id, $workspaceId),
            'fromName' => getSetting('email_from_name', config('app.name'), $user->id, $workspaceId)
        ];
        
        // Get the actual password (not masked)
        $password = getSetting('email_password', '', $user->id, $workspaceId);
        
        try {
            // Configure mail settings for this request only
            config([
                'mail.default' => $settings['driver'],
                'mail.mailers.smtp.host' => $settings['host'],
                'mail.mailers.smtp.port' => $settings['port'],
                'mail.mailers.smtp.encryption' => $settings['encryption'] === 'none' ? null : $settings['encryption'],
                'mail.mailers.smtp.username' => $settings['username'],
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $settings['fromAddress'],
                'mail.from.name' => $settings['fromName'],
            ]);

            // Send test email
            Mail::to($request->email)->send(new TestMail());

            return redirect()->back()->with('success', __('Test email sent successfully to :email', ["email" =>  $request->email]));
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => __('Failed to send test email: :message' , ["message" => $e->getMessage()])]);
        }
    }

    /**
     * Get a setting value for a user.
     *
     * @param  int  $userId
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */

}