<?php

namespace App\Http\Controllers;

use App\Models\TaskStage;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskStageController extends Controller
{
    use HasPermissionChecks;
    public function index(): Response
    {
        $this->authorizePermission('task_manage_stages');
        
        $user = auth()->user();
        $stages = TaskStage::forWorkspace($user->current_workspace_id)
            ->withCount('tasks')
            ->with(['tasks' => function($query) {
                $query->select('id', 'task_stage_id', 'title', 'priority');
            }])
            ->ordered()
            ->get();

        return Inertia::render('task-stages/Index', [
            'stages' => $stages,
            'permissions' => [
                'create' => $this->checkPermission('task_manage_stages'),
                'update' => $this->checkPermission('task_manage_stages'),
                'delete' => $this->checkPermission('task_manage_stages'),
                'reorder' => $this->checkPermission('task_manage_stages'),
                'set_default' => $this->checkPermission('task_manage_stages'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('task_manage_stages');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $maxOrder = TaskStage::forWorkspace(auth()->user()->current_workspace_id)->max('order') ?? 0;

        TaskStage::create([
            ...$validated,
            'workspace_id' => auth()->user()->current_workspace_id,
            'order' => $maxOrder + 1,
            'is_default' => false
        ]);

        return back()->with('success', __('Task stage created successfully!'));
    }

    public function update(Request $request, TaskStage $taskStage)
    {
        $this->authorizePermission('task_manage_stages');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        $taskStage->update($validated);

        return back()->with('success', __('Task stage updated successfully!'));
    }

    public function destroy(TaskStage $taskStage)
    {
        $this->authorizePermission('task_manage_stages');
        
        // Don't allow deletion if stage has tasks
        if ($taskStage->tasks()->count() > 0) {
            return back()->with('error', __('Cannot delete stage with existing tasks'));
        }

        $taskStage->delete();

        return back()->with('success', __('Task stage deleted successfully!'));
    }

    public function reorder(Request $request)
    {
        $this->authorizePermission('task_manage_stages');
        
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:task_stages,id',
            'stages.*.order' => 'required|integer|min:1'
        ]);

        foreach ($validated['stages'] as $stageData) {
            TaskStage::where('id', $stageData['id'])
                ->where('workspace_id', auth()->user()->current_workspace_id)
                ->update(['order' => $stageData['order']]);
        }

        return back()->with('success', __('Task stages reordered successfully!'));
    }

    public function setDefault(TaskStage $taskStage)
    {
        $this->authorizePermission('task_manage_stages');
        
        // Remove default from all stages in workspace
        TaskStage::where('workspace_id', auth()->user()->current_workspace_id)
            ->update(['is_default' => false]);
        
        // Set this stage as default
        $taskStage->update(['is_default' => true]);
        
        return back()->with('success', __('Default stage updated successfully!'));
    }
}