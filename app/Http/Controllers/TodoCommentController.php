<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoComment;
use Illuminate\Http\Request;
use App\Traits\HasPermissionChecks;

class TodoCommentController extends Controller
{
    use HasPermissionChecks;

    public function store(Request $request, Todo $todo)
    {
        $this->authorizePermission('todo_comment_create');
        
        $request->validate([
            'comment' => 'required|string',
        ]);

        $comment = $todo->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->comment,
        ]);

        $comment->load('user');

        if (!config('app.is_demo', true)) {
            event(new \App\Events\TodoCommentAdded($todo, 'comment'));
        }

        return redirect()->back();
    }

    public function update(Request $request, TodoComment $todoComment)
    {
        $this->authorizePermission('todo_comment_update');
        
        if ($todoComment->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'comment' => 'required|string',
        ]);

        $todoComment->update([
            'comment' => $request->comment,
        ]);

        return redirect()->back();
    }

    public function destroy(TodoComment $todoComment)
    {
        $this->authorizePermission('todo_comment_delete');
        
        if ($todoComment->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $todoComment->delete();

        return redirect()->back();
    }
}
