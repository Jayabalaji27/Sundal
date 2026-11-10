<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        // Shared with the AI assistant.
        app(\App\Actions\Projects\CreateMilestone::class)->handle(auth()->user(), $project, $validated);

        return back();
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        // Shared with the AI assistant.
        app(\App\Actions\Projects\UpdateMilestone::class)->handle(auth()->user(), $milestone, $validated);

        return back();
    }

    public function destroy(Project $project, ProjectMilestone $milestone)
    {
        $milestone->delete();
        $project->logActivity('milestone_deleted', "Milestone '{$milestone->title}' was deleted");

        return back();
    }

    public function updateStatus(Request $request, Project $project, ProjectMilestone $milestone)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,overdue'
        ]);

        $oldStatus = $milestone->status;
        $milestone->update($validated);

        if ($validated['status'] === 'completed') {
            $milestone->markCompleted();
        }
        
        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\MilestoneStatusUpdated($milestone, $oldStatus, $validated['status']));
        }

        return back();
    }

    public function reorder(Request $request, Project $project)
    {
        $validated = $request->validate([
            'milestones' => 'required|array',
            'milestones.*.id' => 'required|exists:project_milestones,id',
            'milestones.*.order' => 'required|integer'
        ]);

        foreach ($validated['milestones'] as $milestoneData) {
            ProjectMilestone::where('id', $milestoneData['id'])
                ->update(['order' => $milestoneData['order']]);
        }

        $project->logActivity('milestones_reordered', 'Project milestones were reordered');

        return back();
    }
}