<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskChecklist;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date'
        ]);

        $maxOrder = $task->checklists()->max('order') ?? 0;

        TaskChecklist::create([
            'task_id' => $task->id,
            'title' => $validated['title'],
            'assigned_to' => $validated['assigned_to'],
            'due_date' => $validated['due_date'],
            'order' => $maxOrder + 1,
            'is_completed' => false,
            'created_by' => auth()->id()
        ]);

        return back();
    }

    public function update(Request $request, TaskChecklist $taskChecklist)
    {
        // Check if user can update checklist
        if (!$taskChecklist->canBeUpdatedBy(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date'
        ]);

        $taskChecklist->update($validated);

        return back();
    }

    public function destroy(TaskChecklist $taskChecklist)
    {
        // Check if user can delete checklist
        if (!$taskChecklist->canBeDeletedBy(auth()->user())) {
            abort(403);
        }

        $taskChecklist->delete();

        // Update parent task progress
        $taskChecklist->task->update([
            'progress' => $taskChecklist->task->calculateProgress()
        ]);

        return back();
    }

    public function toggle(TaskChecklist $taskChecklist)
    {
        $taskChecklist->toggle();

        return back();
    }
}