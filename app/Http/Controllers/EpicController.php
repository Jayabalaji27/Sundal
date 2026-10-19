<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use App\Traits\PresentsAgileBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Epics of a project (as in Zoho Sprints and Jira): large pieces of work that
 * group tasks and bugs across sprints. Managers create and edit them; anyone
 * who plans sprints files items under them. Permissions follow the sprints'.
 */
class EpicController extends Controller
{
    use PresentsAgileBoard;

    private function abortUnlessEpicVisible(Epic $epic): void
    {
        abort_if($epic->workspace_id !== auth()->user()->current_workspace_id, 404);
        abort_if(!Project::visibleTo(auth()->user())->whereKey($epic->project_id)->exists(), 404);
    }

    private function rules(Request $request, Project $project): array
    {
        $team = $project->members()->pluck('user_id')->push($project->created_by)->filter()->unique()->all();

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'color' => ['nullable', Rule::in(Epic::COLORS)],
            'status' => ['nullable', Rule::in(Epic::STATUSES)],
            // The owner is someone on the project.
            'owner_id' => ['nullable', Rule::in($team)],
            'start_date' => 'nullable|date',
            'end_date' => ['nullable', 'date', Rule::when($request->filled('start_date'), 'after_or_equal:start_date')],
        ];
    }

    /** An epic as the page shows it: progress counts its items done (in a completed stage). */
    private function present(Epic $epic): array
    {
        $tasks = $epic->tasks;
        $done = $tasks->filter(fn (Task $task) => $task->isCompleted());
        $owner = $epic->owner;

        return [
            'id' => $epic->id,
            'name' => $epic->name,
            'description' => $epic->description,
            'color' => $epic->color,
            'status' => $epic->status,
            'start_date' => $epic->start_date?->toDateString(),
            'end_date' => $epic->end_date?->toDateString(),
            'owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'avatar' => check_file($owner->avatar) ? get_file($owner->avatar) : null,
            ] : null,
            'item_count' => $tasks->count(),
            'done_count' => $done->count(),
            'bug_count' => $tasks->where('type', 'bug')->count(),
            'points' => (int) $tasks->sum('story_points'),
            'done_points' => (int) $done->sum('story_points'),
            'tasks' => $tasks->sortBy('id')->map(fn (Task $task) => $this->taskCard($task))->values(),
        ];
    }

    public function index(Project $project)
    {
        $this->abortUnlessProjectVisible($project);

        $epics = $project->epics()
            ->with(['owner:id,name,avatar', 'tasks' => fn ($q) => $q->with($this->taskCardRelations())])
            // Open epics first, then by start date and name.
            ->orderByRaw("FIELD(status, 'open', 'done')")
            ->orderByRaw('start_date IS NULL, start_date')
            ->orderBy('name')
            ->get();

        $taskForm = $this->taskFormData($project);

        return Inertia::render('projects/Epics', [
            'project' => $project->shellSummary(),
            'epics' => $epics->map(fn (Epic $epic) => $this->present($epic))->values(),
            // Items not in any epic yet, to add to one.
            'unfiled' => $project->tasks()->whereNull('epic_id')->orderByDesc('id')->get(['id', 'title', 'type'])
                ->map(fn (Task $task) => $task->only('id', 'title', 'type'))->values(),
            'colors' => Epic::COLORS,
            'team' => $project->members()->with('user:id,name')->get()->pluck('user')->filter()
                ->push($project->creator)->filter()->unique('id')->map(fn ($user) => $user->only('id', 'name'))->values(),
            'stages' => $this->boardStages(),
            'taskForm' => $taskForm,
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $this->abortUnlessProjectVisible($project);
        $data = $request->validate($this->rules($request, $project));

        Epic::create([
            ...$data,
            'color' => $data['color'] ?? Epic::COLORS[$project->epics()->count() % count(Epic::COLORS)],
            'status' => $data['status'] ?? 'open',
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
        ]);

        return back()->with('success', __('Epic created.'));
    }

    public function update(Request $request, Epic $epic)
    {
        $this->abortUnlessEpicVisible($epic);
        $data = $request->validate($this->rules($request, $epic->project));
        $epic->update([...$data, 'color' => $data['color'] ?? $epic->color, 'status' => $data['status'] ?? $epic->status]);

        return back()->with('success', __('Epic updated.'));
    }

    /** Its items stay in the project, just without an epic. */
    public function destroy(Epic $epic)
    {
        $this->abortUnlessEpicVisible($epic);
        // Unlink first (the foreign key also does this where the database enforces it).
        DB::transaction(function () use ($epic) {
            $epic->tasks()->update(['epic_id' => null]);
            $epic->delete();
        });

        return back()->with('success', __('Epic deleted. Its items are still in the project.'));
    }

    public function addTask(Request $request, Epic $epic)
    {
        $this->abortUnlessEpicVisible($epic);
        $data = $request->validate([
            'task_id' => ['required', Rule::exists('tasks', 'id')->where('project_id', $epic->project_id)],
        ]);
        // A model save, so the change is in the item's history.
        Task::findOrFail($data['task_id'])->update(['epic_id' => $epic->id]);

        return back()->with('success', __('Added to :name.', ['name' => $epic->name]));
    }

    public function removeTask(Request $request, Epic $epic)
    {
        $this->abortUnlessEpicVisible($epic);
        $data = $request->validate(['task_id' => 'required|integer']);
        Task::whereKey($data['task_id'])->where('epic_id', $epic->id)->first()?->update(['epic_id' => null]);

        return back()->with('success', __('Removed from :name.', ['name' => $epic->name]));
    }
}
