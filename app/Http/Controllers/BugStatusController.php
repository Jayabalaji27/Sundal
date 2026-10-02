<?php

namespace App\Http\Controllers;

use App\Models\BugStatus;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BugStatusController extends Controller
{
    use HasPermissionChecks;
    public function index(): Response
    {
        $this->authorizePermission('bug_manage_statuses');
        
        $user = auth()->user();
        $statuses = BugStatus::forWorkspace($user->current_workspace_id)
            ->withCount('bugs')
            ->with(['bugs' => function($query) {
                $query->select('id', 'bug_status_id', 'title', 'priority', 'severity');
            }])
            ->ordered()
            ->get();

        return Inertia::render('bug-statuses/Index', [
            'statuses' => $statuses,
            'permissions' => [
                'create' => $this->checkPermission('bug_manage_statuses'),
                'update' => $this->checkPermission('bug_manage_statuses'),
                'delete' => $this->checkPermission('bug_manage_statuses'),
                'reorder' => $this->checkPermission('bug_manage_statuses'),
                'set_default' => $this->checkPermission('bug_manage_statuses'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('bug_manage_statuses');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $maxOrder = BugStatus::forWorkspace(auth()->user()->current_workspace_id)->max('order') ?? 0;

        BugStatus::create([
            ...$validated,
            'workspace_id' => auth()->user()->current_workspace_id,
            'order' => $maxOrder + 1,
            'is_default' => false
        ]);

        return back()->with('success', __('Bug status created successfully!'));
    }

    public function update(Request $request, BugStatus $bugStatus)
    {
        $this->authorizePermission('bug_manage_statuses');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $bugStatus->update($validated);

        return back()->with('success', __('Bug status updated successfully!'));
    }

    public function destroy(BugStatus $bugStatus)
    {
        $this->authorizePermission('bug_manage_statuses');
        
        // Don't allow deletion if status has bugs
        if ($bugStatus->bugs()->count() > 0) {
            return back()->with('error', __('Cannot delete status with existing bugs'));
        }

        $bugStatus->delete();

        return back()->with('success', __('Bug status deleted successfully!'));
    }

    public function reorder(Request $request)
    {
        $this->authorizePermission('bug_manage_statuses');
        
        $validated = $request->validate([
            'statuses' => 'required|array',
            'statuses.*.id' => 'required|exists:bug_statuses,id',
            'statuses.*.order' => 'required|integer|min:1'
        ]);

        foreach ($validated['statuses'] as $statusData) {
            BugStatus::where('id', $statusData['id'])
                ->where('workspace_id', auth()->user()->current_workspace_id)
                ->update(['order' => $statusData['order']]);
        }

        return back()->with('success', __('Bug statuses reordered successfully!'));
    }

    public function setDefault(BugStatus $bugStatus)
    {
        $this->authorizePermission('bug_manage_statuses');
        
        // Remove default from all statuses in workspace
        BugStatus::where('workspace_id', auth()->user()->current_workspace_id)
            ->update(['is_default' => false]);
        
        // Set this status as default
        $bugStatus->update(['is_default' => true]);
        
        return back()->with('success', __('Default bug status updated successfully!'));
    }
}