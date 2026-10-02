<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_pinned' => 'boolean'
        ]);

        $note = $project->notes()->create([
            ...$validated,
            'created_by' => auth()->id()
        ]);

        $project->logActivity('note_created', "Note '{$note->title}' was created");

        return back();
    }

    public function update(Request $request, Project $project, ProjectNote $note)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_pinned' => 'boolean'
        ]);

        $note->update([
            ...$validated,
            'updated_by' => auth()->id()
        ]);

        $project->logActivity('note_updated', "Note '{$note->title}' was updated");

        return back();
    }

    public function destroy(Project $project, ProjectNote $note)
    {
        $note->delete();
        $project->logActivity('note_deleted', "Note '{$note->title}' was deleted");

        return back();
    }

    public function togglePin(Project $project, ProjectNote $note)
    {
        $note->togglePin();
        return back();
    }
}