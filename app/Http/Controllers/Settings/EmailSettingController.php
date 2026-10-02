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
            'fromName' => getSetting('email_from_name', 'WorkDo System', $user->id, $workspaceId)
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
    public function updateEmailSettings(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'provider' => 'required|string',
            'driver' => 'required|string',
            'host' => 'required|string',
            'port' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
            'encryption' => 'required|string',
            'fromAddress' => 'required|email',
            'fromName' => 'required|string',
        ]);

        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        updateSetting('email_provider', $validated['provider'], $user->id, $workspaceId);
        updateSetting('email_driver', $validated['driver'], $user->id, $workspaceId);
        updateSetting('email_host', $validated['host'], $user->id, $workspaceId);
        updateSetting('email_port', $validated['port'], $user->id, $workspaceId);
        updateSetting('email_username', $validated['username'], $user->id, $workspaceId);
        
        // Only update password if provided and not masked
        if (!empty($validated['password']) && $validated['password'] !== '••••••••••••') {
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

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

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
            'encryption' => getSetting('email_encryption', 'tls', $user->id, $workspaceId),
            'fromAddress' => getSetting('email_from_address', 'noreply@example.com', $user->id, $workspaceId),
            'fromName' => getSetting('email_from_name', 'WorkDo System', $user->id, $workspaceId)
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
            return redirect()->back()->with('error', __('Failed to send test email: :message' , ["message" => $e->getMessage()]));
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