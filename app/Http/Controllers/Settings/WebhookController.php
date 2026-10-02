<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Workspace;

class WebhookController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $webhooks = Webhook::where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->get();
        return response()->json($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'module' => 'required|in:Workspace Invitation,New Project,New Task,New Budget,New Invoice',
            'method' => 'required|in:GET,POST',
            'url' => 'required|url',
        ]);

        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        $webhook = Webhook::create([
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'module' => $request->module,
            'method' => $request->method,
            'url' => $request->url,
        ]);

        return response()->json([
            'webhook' => $webhook,
            'message' => 'Webhook created successfully'
        ]);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        if ($webhook->user_id !== $user->id || $webhook->workspace_id != $workspaceId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'module' => 'required|in:Workspace Invitation,New Project,New Task,New Budget,New Invoice',
            'method' => 'required|in:GET,POST',
            'url' => 'required|url',
        ]);

        $webhook->update([
            'module' => $request->module,
            'method' => $request->method,
            'url' => $request->url,
        ]);

        return response()->json([
            'webhook' => $webhook,
            'message' => 'Webhook updated successfully'
        ]);
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $user = auth()->user();
        $workspaceId = null;
        
        if ($user->type === 'company') {
            $workspaceId = $user->current_workspace_id;
        }
        
        if ($webhook->user_id !== $user->id || $webhook->workspace_id != $workspaceId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted successfully']);
    }
}