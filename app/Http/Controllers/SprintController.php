<?php
namespace App\Http\Controllers;

use App\Actions\ActionException;
use App\Actions\Sprints\AddTasksToSprint;
use App\Actions\Sprints\CompleteSprint;
use App\Actions\Sprints\StartSprint;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Traits\PresentsAgileBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Sprints of a project. Two pages, as in Zoho Sprints / Azure DevOps:
 *  - Backlog (backlog): the work nobody has planned yet, with the open sprints
 *    as places to plan it into;
 *  - Sprints (index): the running, planned and finished sprints, where they
 *    are created, started and completed.
 * Completing a sprint sends unfinished tasks back to the backlog or on to the
 * next sprint. One sprint per project is active at a time.
 */
class SprintController extends Controller
{
    use PresentsAgileBoard;

    private function ws(): int { return auth()->user()->current_workspace_id; }

    private function abortUnlessSprintVisible(Sprint $sprint): void
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        abort_if(! Project::visibleTo(auth()->user())->whereKey($sprint->project_id)->exists(), 404);
    }

    /** end_date must not be before start_date, when both are given. */
    private function dateRules(Request $request): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => ['nullable', 'date', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
        ];
    }

    /** The active sprint first, then the planned ones, each with its tasks. */
    private function openSprints(Project $project): Collection
    {
        return Sprint::where('project_id', $project->id)
            ->whereIn('status', ['active', 'planning'])
            ->with(['tasks' => fn ($q) => $q->with($this->taskCardRelations())->orderBy('sprint_tasks.id')])
            ->orderByRaw("FIELD(status,'active','planning')")
            ->orderBy('id')
            ->get();
    }

    /** Open tasks of the project that are in no open sprint. */
    private function backlogTasks(Project $project, Collection $openSprints): Collection
    {
        $inOpenSprint = DB::table('sprint_tasks')->whereIn('sprint_id', $openSprints->pluck('id'))->pluck('task_id');

        // Inside one project the whole team sees all of its tasks (Task::visibleTo's project rule).
        return Task::visibleTo(auth()->user(), false)
            ->forProject($project->id)
            ->open()
            ->whereNotIn('id', $inOpenSprint)
            ->with($this->taskCardRelations())
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->latest()
            ->get();
    }

    // Sprints: running and planned sprints with their tasks, then the finished ones.
    public function index(Project $project)
    {
        $this->abortUnlessProjectVisible($project);
        $openSprints = $this->openSprints($project);
        $backlog = $this->backlogTasks($project, $openSprints);

        $completed = Sprint::where('project_id', $project->id)
            ->where('status', 'completed')
            ->with(['tasks' => fn ($q) => $q->with($this->taskCardRelations())])
            ->orderByDesc('end_date')->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Sprint $sprint) => $this->sprintSummary($sprint));

        // Velocity: average story points finished per sprint over the last three sprints.
        $recent = $completed->take(3);
        $velocity = $recent->isNotEmpty() && $recent->sum('done_points') > 0
            ? round($recent->avg('done_points'), 1)
            : null;

        return Inertia::render('sprints/Index', [
            'project' => $project->shellSummary(),
            'sprints' => $openSprints->map(fn (Sprint $sprint) => [
                ...$this->sprintSummary($sprint),
                'tasks' => $sprint->tasks->map(fn (Task $task) => $this->taskCard($task))->values(),
            ])->values(),
            'backlogSummary' => ['tasks' => $backlog->count(), 'points' => (int) $backlog->sum('story_points')],
            'completedSprints' => $completed->values(),
            'velocity' => $velocity,
            'nextSprintName' => CompleteSprint::nextName($project->id),
            'stages' => $this->boardStages(),
            'taskForm' => $this->taskFormData($project),
        ]);
    }

    // Backlog: unplanned work, and the open sprints it can be planned into.
    public function backlog(Project $project)
    {
        $this->abortUnlessProjectVisible($project);
        $openSprints = $this->openSprints($project);

        return Inertia::render('projects/Backlog', [
            'project' => $project->shellSummary(),
            'backlog' => $this->backlogTasks($project, $openSprints)->map(fn (Task $task) => $this->taskCard($task))->values(),
            // Each open sprint with its items, so the plan shows beside the backlog.
            'sprints' => $openSprints->map(fn (Sprint $sprint) => [
                ...$this->sprintSummary($sprint),
                'tasks' => $sprint->tasks->map(fn (Task $task) => $this->taskCard($task))->values(),
            ])->values(),
            'nextSprintName' => CompleteSprint::nextName($project->id),
            'stages' => $this->boardStages(),
            'taskForm' => $this->taskFormData($project),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->abortUnlessProjectVisible($project);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'goal' => 'nullable|string|max:1000',
            ...$this->dateRules($request),
        ]);

        Sprint::create([
            ...$data,
            'project_id' => $project->id,
            'workspace_id' => $this->ws(),
            'status' => 'planning',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('Sprint created.'));
    }

    // Name, goal and dates only: status changes go through start() and complete().
    public function update(Request $request, Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'goal'       => 'nullable|string|max:1000',
            ...$this->dateRules($request),
        ]);
        $sprint->update($data);
        return back()->with('success', __('Sprint updated.'));
    }

    // Its tasks drop back to the backlog (sprint_tasks rows go with the sprint).
    public function destroy(Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $sprint->delete();
        return back()->with('success', __('Sprint deleted.'));
    }

    // Sprint report: burndown and totals.
    public function show(Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $sprint->load(['project', 'tasks' => fn ($q) => $q->with($this->taskCardRelations())]);

        return Inertia::render('sprints/Show', [
            'project'  => $sprint->project->shellSummary(),
            'sprint'   => [
                ...$this->sprintSummary($sprint),
                'tasks' => $sprint->tasks->map(fn (Task $task) => $this->taskCard($task))->values(),
            ],
            'stages'   => $this->boardStages(),
            'burndown' => $sprint->burndownData(),
        ]);
    }

    public function addTask(Request $request, Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $request->validate(['task_id' => 'required|exists:tasks,id']);
        try {
            // Shared with the AI assistant: same-project tasks only, records who added it.
            app(AddTasksToSprint::class)->handle(auth()->user(), $sprint, [Task::findOrFail($request->task_id)]);
        } catch (ActionException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', __('Task moved to :name.', ['name' => $sprint->name]));
    }

    // Back to the backlog.
    public function removeTask(Request $request, Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $request->validate(['task_id' => 'required|exists:tasks,id']);
        $sprint->tasks()->detach($request->task_id);
        return back()->with('success', __('Task moved to the backlog.'));
    }

    // One active sprint per project. Dates (and goal) can be confirmed on start.
    public function start(Request $request, Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);
        $data = $request->validate([
            'goal' => 'nullable|string|max:1000',
            ...$this->dateRules($request),
        ]);

        try {
            app(StartSprint::class)->handle($sprint, $data);
        } catch (ActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':name started.', ['name' => $sprint->name]));
    }

    /**
     * Unfinished tasks (not in a completed stage) leave the sprint for the
     * backlog, a planned sprint (move_to = its id) or a new sprint (move_to = new).
     */
    public function complete(Request $request, Sprint $sprint)
    {
        $this->abortUnlessSprintVisible($sprint);

        $moveTo = (string) $request->input('move_to', 'backlog');
        if (!in_array($moveTo, ['backlog', 'new'], true)) {
            $moveTo = Sprint::where('project_id', $sprint->project_id)->where('status', 'planning')->find((int) $moveTo);
            if (!$moveTo) {
                return back()->with('error', __('Choose a planned sprint of this project for the unfinished tasks.'));
            }
        }

        try {
            [$count, $targetName] = app(CompleteSprint::class)->handle(auth()->user(), $sprint, $moveTo);
        } catch (ActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', CompleteSprint::message($sprint, $count, $targetName));
    }
}
