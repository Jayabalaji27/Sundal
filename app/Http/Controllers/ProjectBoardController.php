<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Traits\PresentsAgileBoard;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The project Board: task stages as columns. Shows the active sprint when
 * there is one (scope=sprint), otherwise every task of the project (scope=all).
 * Cards move between columns through the existing tasks.change-stage route.
 */
class ProjectBoardController extends Controller
{
    use PresentsAgileBoard;

    public function show(Request $request, Project $project)
    {
        $this->abortUnlessProjectVisible($project);
        $user = auth()->user();

        $activeSprint = Sprint::where('project_id', $project->id)->where('status', 'active')
            ->with(['tasks' => fn ($q) => $q->with($this->taskCardRelations())])
            ->first();

        $scope = $request->input('scope', $activeSprint ? 'sprint' : 'all');
        if ($scope !== 'all' && !$activeSprint) {
            $scope = 'all';
        }
        $search = trim((string) $request->input('search', ''));
        $mine = $request->boolean('mine');
        // Tasks and bugs share the board; "type" narrows it to one of them.
        $type = in_array($request->input('type'), Task::TYPES, true) ? $request->input('type') : 'all';

        // Inside one project the whole team sees all of its tasks (Task::visibleTo's project rule).
        $tasks = Task::visibleTo($user, false)
            ->forProject($project->id)
            ->when($scope === 'sprint', fn ($q) => $q->whereIn('id', $activeSprint->tasks->pluck('id')))
            ->when($mine, fn ($q) => $q->where('assigned_to', $user->id))
            ->ofType($type)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%' . $search . '%'))
            ->with($this->taskCardRelations())
            ->latest('updated_at')
            ->get();

        return Inertia::render('projects/Board', [
            'project' => $project->shellSummary(),
            'stages' => $this->boardStages(),
            'tasks' => $tasks->map(fn (Task $task) => $this->taskCard($task))->values(),
            'activeSprint' => $activeSprint ? $this->sprintSummary($activeSprint) : null,
            'hasPlannedSprints' => Sprint::where('project_id', $project->id)->where('status', 'planning')->exists(),
            'filters' => ['scope' => $scope, 'mine' => $mine, 'search' => $search, 'type' => $type],
            'taskForm' => $this->taskFormData($project),
        ]);
    }
}
