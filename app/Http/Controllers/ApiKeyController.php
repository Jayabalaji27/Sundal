<?php
namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::where('workspace_id', auth()->user()->current_workspace_id)
            ->with('user:id,name')
            ->latest()->get();
        return Inertia::render('api-keys/Index', ['apiKeys' => $keys]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->current_workspace_id) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $request->validate(['name' => 'required|string|max:255', 'expires_at' => 'nullable|date|after:today']);

        $generated = ApiKey::generate();

        ApiKey::create([
            'workspace_id' => auth()->user()->current_workspace_id,
            'user_id'      => auth()->id(),
            'name'         => $request->name,
            'key'          => $generated['hashed'],
            'key_prefix'   => $generated['prefix'],
            'expires_at'   => $request->expires_at,
        ]);

        // Store raw key in session flash — picked up by HandleInertiaRequests
        return back()
            ->with('new_api_key', $generated['raw'])
            ->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function destroy(ApiKey $apiKey)
    {
        abort_if($apiKey->workspace_id !== auth()->user()->current_workspace_id, 404);
        $apiKey->delete();
        return back()->with('success', 'API key revoked.');
    }

    public function toggle(ApiKey $apiKey)
    {
        abort_if($apiKey->workspace_id !== auth()->user()->current_workspace_id, 404);
        $apiKey->update(['is_active' => !$apiKey->is_active]);
        return back()->with('success', $apiKey->is_active ? 'Key activated.' : 'Key deactivated.');
    }
}
