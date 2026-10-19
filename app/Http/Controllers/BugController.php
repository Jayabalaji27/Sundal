<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bugs are tasks of type "bug" (see the merge_bugs_into_tasks migration), so they
 * sit on the same Board, Backlog, Sprints and Tasks list as the rest of the work.
 * These two routes stay only so old links (bookmarks, emails) still land well.
 */
class BugController extends Controller
{
    /** The Tasks list showing only bugs; the project and "create" carry over. */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('tasks.index', array_filter([
            'type' => 'bug',
            'project_id' => $request->integer('project_id') ?: null,
            'action' => $request->input('action') === 'create' ? 'create' : null,
        ]));
    }

    /** An old bug link opens the task it became. */
    public function show(int $bug): RedirectResponse
    {
        $task = Task::where('legacy_bug_id', $bug)->first() ?? abort(404);

        return redirect()->route('tasks.show', $task);
    }
}
