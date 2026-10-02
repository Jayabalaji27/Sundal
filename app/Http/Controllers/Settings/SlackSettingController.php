<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SlackSetting;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SlackSettingController extends Controller
{
    protected $slackService;

    public function __construct(SlackService $slackService)
    {
        $this->slackService = $slackService;
    }

    public function getSlackSettings()
    {
        $user = Auth::user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $settings = SlackSetting::getSettings($user->id, $workspaceId);
        
        $settings['slack_enabled'] = $settings['slack_enabled'] === 'true';
        
        $defaultNotifications = [
            'new_project' => false,
            'new_task' => false,
            'task_stage_updated' => false,
            'new_milestone' => false,
            'milestone_status_updated' => false,
            'new_task_comment' => false,
            'new_invoice' => false,
            'invoice_status_updated' => false,
        ];
        
        $settings['slack_notifications'] = array_merge($defaultNotifications, $settings['slack_notifications']);

        return response()->json($settings);
    }

    public function updateSlackSettings(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'slack_enabled' => 'boolean',
            'slack_webhook_url' => 'nullable|url',
            'slack_notifications' => 'array'
        ]);

        $workspaceId = null;
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }

        SlackSetting::updateSettings($user->id, $workspaceId, $validated);

        return redirect()->back()->with('success', __('Slack settings updated successfully'));
    }

    public function testSlackWebhook(Request $request)
    {
        $request->validate([
            'webhook_url' => 'required|url',
            'debug' => 'boolean'
        ]);

        $debug = $request->boolean('debug', false);
        
        $result = $this->slackService->sendTestMessage($request->webhook_url, $debug);

        if ($result['success']) {
            return redirect()->back()->with('success', __('Test message sent successfully to Slack!'));
        }

        return redirect()->back()->with('error', __('Failed to send test message: :message', ['message' => $result['error'] ?? 'Unknown error']));
    }
}