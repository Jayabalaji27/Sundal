<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array'
        ]);

        $taskComment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
            'mentions' => $validated['mentions'] ?? []
        ]);

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\TaskCommentAdded($taskComment));
        }

        return back();
    }

    public function update(Request $request, TaskComment $taskComment)
    {
        // Check if user can update comment
        if (!$taskComment->canBeUpdatedBy(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array'
        ]);

        $taskComment->update($validated);

        return back();
    }

    public function destroy(TaskComment $taskComment)
    {
        // Check if user can delete comment
        if (!$taskComment->canBeDeletedBy(auth()->user())) {
            abort(403);
        }

        $taskComment->delete();

        return back();
    }
}