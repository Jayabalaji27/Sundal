<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ClientPortalController extends Controller
{
    // ── Admin: toggle portal on/off & generate token ──────────────────────
    public function toggle(Request $request, Project $project)
    {
        abort_if($project->workspace_id !== auth()->user()->current_workspace_id, 403);

        if ($request->portal_enabled && !$project->portal_token) {
            $project->update([
                'portal_enabled' => true,
                'portal_token'   => Str::random(48),
                'portal_message' => $request->portal_message,
            ]);
        } else {
            $project->update([
                'portal_enabled' => $request->portal_enabled,
                'portal_message' => $request->portal_message,
            ]);
        }

        return back()->with('success', $request->portal_enabled ? 'Client portal enabled.' : 'Client portal disabled.');
    }

    public function regenerateToken(Project $project)
    {
        abort_if($project->workspace_id !== auth()->user()->current_workspace_id, 403);
        $project->update(['portal_token' => Str::random(48)]);
        return back()->with('success', 'Portal link regenerated. Share the new link with your client.');
    }

    // ── Public: show portal (no auth) ─────────────────────────────────────
    public function show(string $token)
    {
        $project = Project::where('portal_token', $token)
            ->where('portal_enabled', true)
            ->with([
                'workspace:id,name',
                'milestones' => fn($q) => $q->orderBy('due_date'),
            ])
            ->firstOrFail();

        $activities = \App\Models\ProjectActivity::where('project_id', $project->id)
            ->with('user:id,name')
            ->latest()
            ->limit(15)
            ->get();

        // Tasks summary (no details — just counts per stage); bugs are counted below
        $taskStats = \DB::table('tasks')
            ->join('task_stages','tasks.task_stage_id','=','task_stages.id')
            ->where('tasks.project_id', $project->id)
            ->where('tasks.type', 'task')
            ->select('task_stages.name as stage', \DB::raw('count(*) as count'))
            ->groupBy('task_stages.name')
            ->get();

        // Bugs summary
        $bugs = Task::bugs()->where('project_id', $project->id);
        $bugStats = [
            'total'  => (clone $bugs)->count(),
            'open'   => (clone $bugs)->whereDoesntHave('taskStage', fn ($q) => $q->where('is_completed', true))->count(),
        ];

        // Invoices (public-safe fields only)
        $invoices = \App\Models\Invoice::where('project_id', $project->id)
            ->select('id','invoice_number','total_amount','status','due_date','created_at')
            ->latest()->get();

        return Inertia::render('portal/Show', [
            'project'    => array_merge($project->only('id','title','description','status','priority','progress','start_date','deadline','portal_message'), [
                'workspace_name' => $project->workspace->name,
                'milestones'     => $project->milestones,
                'activities'     => $activities,
            ]),
            'taskStats'  => $taskStats,
            'bugStats'   => $bugStats,
            'invoices'   => $invoices,
            'token'      => $token,
        ]);
    }

    // ── Public: client submits a bug from portal ──────────────────────────
    public function submitBug(Request $request, string $token)
    {
        $project = Project::where('portal_token', $token)->where('portal_enabled', true)->firstOrFail();

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'priority'    => 'in:low,medium,high,urgent',
        ]);

        // A bug task in the first stage, so the team sees it on the Board.
        // Portal reports are anonymous, so the project's creator owns them.
        $firstStage = TaskStage::withoutGlobalScope('workspace')->forWorkspace($project->workspace_id)->ordered()->first();

        Task::create([
            'project_id'    => $project->id,
            'type'          => 'bug',
            'severity'      => 'major',
            'title'         => $request->title,
            'description'   => $request->description,
            'priority'      => in_array($request->priority, ['low', 'medium', 'high'], true) ? $request->priority : ($request->priority === 'urgent' ? 'critical' : 'medium'),
            'task_stage_id' => $firstStage?->id,
            'created_by'    => $project->created_by,
            'progress'      => 0,
        ]);

        return back()->with('success', 'Your report has been submitted. We\'ll look into it!');
    }
}
