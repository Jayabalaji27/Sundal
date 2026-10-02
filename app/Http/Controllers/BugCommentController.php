<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\BugComment;
use Illuminate\Http\Request;

class BugCommentController extends Controller
{
    public function store(Request $request, Bug $bug)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array'
        ]);

        BugComment::create([
            'bug_id' => $bug->id,
            'user_id' => auth()->id(),
            'comment' => $validated['comment'],
            'mentions' => $validated['mentions'] ?? []
        ]);

        return back();
    }

    public function update(Request $request, BugComment $bugComment)
    {
        if (!$bugComment->canBeUpdatedBy(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'mentions' => 'nullable|array'
        ]);

        $bugComment->update($validated);

        return back();
    }

    public function destroy(BugComment $bugComment)
    {
        if (!$bugComment->canBeDeletedBy(auth()->user())) {
            abort(403);
        }

        $bugComment->delete();

        return back();
    }
}