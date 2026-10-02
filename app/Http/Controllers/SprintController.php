<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskStage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SprintController extends Controller
{
    private function ws(): int { return auth()->user()->current_workspace_id; }

    public function index(Project $project)
    {
        abort_if($project->workspace_id !== $this->ws(), 404);
        abort_if(! Project::visibleTo(auth()->user())->whereKey($project->id)->exists(), 404);

        $sprints = Sprint::where('project_id', $project->id)
            ->withCount('tasks')
            ->orderByRaw("FIELD(status,'active','planning','completed')")
            ->latest()->get();

        $stages = TaskStage::forWorkspace($this->ws())->ordered()->get(['id','name','color']);

        // Backlog = tasks in this project not in any sprint
        $sprintTaskIds = \DB::table('sprint_tasks')
            ->whereIn('sprint_id', $sprints->pluck('id'))
            ->pluck('task_id');

        $backlog = Task::with(['taskStage:id,name,color','assignedTo:id,name,avatar'])
            ->where('project_id', $project->id)
            ->whereNotIn('id', $sprintTaskIds)
            ->latest()->get();

        return Inertia::render('sprints/Index', [
            'project'  => $project->only('id','title','status'),
            'sprints'  => $sprints,
            'backlog'  => $backlog,
            'stages'   => $stages,
        ]);
    }

    public function update(Request $request, Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        $request->validate([
            'name'       => 'required|string|max:255',
            'goal'       => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'status'     => 'in:planning,active,completed',
        ]);
        $sprint->update($request->only('name','goal','start_date','end_date','status'));
        return back()->with('success', 'Sprint updated.');
    }

    public function destroy(Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        $sprint->delete();
        return back()->with('success', 'Sprint deleted.');
    }

    public function show(Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        abort_if(! Project::visibleTo(auth()->user())->whereKey($sprint->project_id)->exists(), 404);
        $sprint->load(['project:id,title','tasks.taskStage:id,name,color','tasks.assignedTo:id,name,avatar']);

        $stages = TaskStage::forWorkspace($this->ws())->ordered()->get(['id','name','color']);
        $burndown = $sprint->burndownData();

        return Inertia::render('sprints/Show', [
            'sprint'   => $sprint,
            'stages'   => $stages,
            'burndown' => $burndown,
        ]);
    }

    // Add task to sprint
    public function addTask(Request $request, Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        $request->validate(['task_id' => 'required|exists:tasks,id']);
        $sprint->tasks()->syncWithoutDetaching([$request->task_id]);
        return back()->with('success', 'Task added to sprint.');
    }

    // Remove task from sprint (back to backlog)
    public function removeTask(Request $request, Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        $request->validate(['task_id' => 'required|exists:tasks,id']);
        $sprint->tasks()->detach($request->task_id);
        return back()->with('success', 'Task moved to backlog.');
    }

    // Start sprint (set to active, deactivate others)
    public function start(Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        Sprint::where('project_id', $sprint->project_id)->where('status','active')
            ->update(['status' => 'planning']);
        $sprint->update(['status' => 'active', 'start_date' => $sprint->start_date ?? now()->toDateString()]);
        return back()->with('success', 'Sprint started!');
    }

    // Complete sprint
    public function complete(Sprint $sprint)
    {
        abort_if($sprint->workspace_id !== $this->ws(), 404);
        $sprint->update(['status' => 'completed', 'end_date' => $sprint->end_date ?? now()->toDateString()]);
        return back()->with('success', 'Sprint completed.');
    }
}
