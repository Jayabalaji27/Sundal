<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectHealthService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class RiskRadarController extends Controller
{
    use HasPermissionChecks;

    public function index(): \Inertia\Response
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) abort(404);

        $projects = Project::forWorkspace($workspace->id)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->get();

        $service = new ProjectHealthService();
        $results = $projects->map(function (Project $p) use ($service) {
            $h = $service->calculate($p);
            return [
                'id'       => $p->id,
                'title'    => $p->title,
                'status'   => $p->status,
                'deadline' => $p->deadline,
                'progress' => $p->progress,
                'health'   => $h,
            ];
        })->sortBy('health.score')->values();

        $summary = [
            'critical' => $results->where('health.status', 'critical')->count(),
            'at_risk'  => $results->where('health.status', 'at_risk')->count(),
            'healthy'  => $results->where('health.status', 'healthy')->count(),
        ];

        return Inertia::render('risk-radar/index', [
            'projects' => $results,
            'summary'  => $summary,
        ]);
    }

    public function api(): JsonResponse
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) return response()->json([]);

        $projects = Project::forWorkspace($workspace->id)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->get();

        $service = new ProjectHealthService();
        $results = $projects->map(fn(Project $p) => [
            'id'       => $p->id,
            'title'    => $p->title,
            'score'    => $service->calculate($p)['score'],
            'status'   => $service->calculate($p)['status'],
            'factors'  => $service->calculate($p)['factors'],
        ])->sortBy('score')->values();

        return response()->json($results);
    }
}
