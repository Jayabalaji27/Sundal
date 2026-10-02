<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $user = Auth::user();
        $workspace = $user?->currentWorkspace;

        if ($q === '' || !$workspace) {
            return response()->json(['projects' => [], 'tasks' => [], 'bugs' => []]);
        }

        $userWorkspaceRole = $workspace->getMemberRole($user);
        $permissions = $user->getAllPermissions()->pluck('name');

        // No artificial per-category cap on how many matches exist - report the
        // true total alongside a capped preview list so the palette stays fast
        // to render while callers can tell the user "N more, refine your search".
        $resultCap = 50;

        $projects = [];
        $projectsTotal = 0;
        if ($permissions->contains('project_view_any')) {
            $projectMatches = Project::forWorkspace($user->current_workspace_id)
                ->visibleTo($user)
                ->where('title', 'like', "%{$q}%");
            $projectsTotal = $projectMatches->count();
            $projects = $projectMatches->limit($resultCap)->get(['id', 'title'])
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title]);
        }

        $tasks = [];
        $tasksTotal = 0;
        if ($permissions->contains('task_view_any')) {
            $taskQuery = Task::whereHas('project', function ($q2) use ($user) {
                $q2->forWorkspace($user->current_workspace_id)->visibleTo($user);
            })->where('title', 'like', "%{$q}%");

            if ($userWorkspaceRole === 'member') {
                $taskQuery->where(function ($tq) use ($user) {
                    $tq->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
                });
            }

            $tasksTotal = $taskQuery->count();
            $tasks = $taskQuery->limit($resultCap)->get(['id', 'title'])
                ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title]);
        }

        $bugs = [];
        $bugsTotal = 0;
        if ($permissions->contains('bug_view_any')) {
            $bugQuery = Bug::whereHas('project', function ($q2) use ($user) {
                $q2->forWorkspace($user->current_workspace_id)->visibleTo($user);
            })->where('title', 'like', "%{$q}%");

            if ($userWorkspaceRole === 'member') {
                $bugQuery->where(function ($bq) use ($user) {
                    $bq->where('assigned_to', $user->id)->orWhere('reported_by', $user->id);
                });
            }

            $bugsTotal = $bugQuery->count();
            $bugs = $bugQuery->limit($resultCap)->get(['id', 'title'])
                ->map(fn ($b) => ['id' => $b->id, 'title' => $b->title]);
        }

        return response()->json([
            'projects' => $projects,
            'tasks' => $tasks,
            'bugs' => $bugs,
            'totals' => [
                'projects' => $projectsTotal,
                'tasks' => $tasksTotal,
                'bugs' => $bugsTotal,
            ],
        ]);
    }
}
