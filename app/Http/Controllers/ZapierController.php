<?php
namespace App\Http\Controllers;

use App\Models\ZapierHook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ZapierController extends Controller
{
    public const AVAILABLE_EVENTS = [
        'task.created'     => 'Task Created',
        'task.updated'     => 'Task Updated',
        'task.completed'   => 'Task Completed',
        'project.created'  => 'Project Created',
        'project.updated'  => 'Project Updated',
        'bug.created'      => 'Bug Reported',
        'invoice.created'  => 'Invoice Created',
        'form.submitted'   => 'Form Submitted',
    ];

    public function index()
    {
        $hooks = ZapierHook::where('workspace_id', auth()->user()->current_workspace_id)
            ->with('creator:id,name')
            ->latest()->get();

        return Inertia::render('zapier/Index', [
            'hooks'           => $hooks,
            'availableEvents' => self::AVAILABLE_EVENTS,
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->current_workspace_id) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $request->validate([
            'name'  => 'required|string|max:255',
            'url'   => 'required|url',
            'event' => 'required|in:' . implode(',', array_keys(self::AVAILABLE_EVENTS)),
        ]);

        ZapierHook::create([
            'workspace_id' => auth()->user()->current_workspace_id,
            'created_by'   => auth()->id(),
            ...$request->only('name','url','event'),
        ]);

        return back()->with('success', 'Webhook created.');
    }

    public function destroy(ZapierHook $zapierHook)
    {
        abort_if($zapierHook->workspace_id !== auth()->user()->current_workspace_id, 404);
        $zapierHook->delete();
        return back()->with('success', 'Webhook deleted.');
    }

    public function toggle(ZapierHook $zapierHook)
    {
        abort_if($zapierHook->workspace_id !== auth()->user()->current_workspace_id, 404);
        $zapierHook->update(['is_active' => !$zapierHook->is_active]);
        return back()->with('success', 'Webhook updated.');
    }

    public function test(ZapierHook $zapierHook)
    {
        abort_if($zapierHook->workspace_id !== auth()->user()->current_workspace_id, 404);

        try {
            $response = Http::timeout(10)->post($zapierHook->url, [
                'event'        => $zapierHook->event,
                'workspace_id' => $zapierHook->workspace_id,
                'test'         => true,
                'timestamp'    => now()->toISOString(),
                'sample_data'  => ['id' => 1, 'title' => 'Test payload from Swatle'],
            ]);
            $success = $response->successful();
        } catch (\Throwable) {
            $success = false;
        }

        return back()->with($success ? 'success' : 'error', $success ? 'Test ping sent successfully!' : 'Webhook URL did not respond.');
    }
}
