<?php
namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
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

        // Tasks summary (no details — just counts per stage)
        $taskStats = \DB::table('tasks')
            ->join('task_stages','tasks.task_stage_id','=','task_stages.id')
            ->where('tasks.project_id', $project->id)
            ->select('task_stages.name as stage', \DB::raw('count(*) as count'))
            ->groupBy('task_stages.name')
            ->get();

        // Bugs summary
        $bugStats = [
            'total'  => Bug::where('project_id', $project->id)->count(),
            'open'   => Bug::where('project_id', $project->id)->whereHas('bugStatus', fn($q) => $q->where('name','not like','%close%')->where('name','not like','%resolve%'))->count(),
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

        $defaultStatus = \App\Models\BugStatus::where('workspace_id', $project->workspace_id)->first();

        Bug::create([
            'project_id'   => $project->id,
            'workspace_id' => $project->workspace_id,
            'title'        => $request->title,
            'description'  => $request->description,
            'priority'     => $request->priority ?? 'medium',
            'bug_status_id'=> $defaultStatus?->id,
            'reported_by'  => null, // anonymous from portal
            'created_by'   => $project->created_by,
        ]);

        return back()->with('success', 'Your report has been submitted. We\'ll look into it!');
    }
}
