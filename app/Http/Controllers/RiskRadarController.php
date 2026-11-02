<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectHealthService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
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

        $service = new ProjectHealthService();
        $results = $this->openProjects()->map(function (Project $p) use ($service) {
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

        if (!auth()->user()->currentWorkspace) return response()->json([]);

        $service = new ProjectHealthService();
        $results = $this->openProjects()->map(function (Project $p) use ($service) {
            $health = $service->calculate($p);
            return [
                'id'      => $p->id,
                'title'   => $p->title,
                'score'   => $health['score'],
                'status'  => $health['status'],
                'factors' => $health['factors'],
            ];
        })->sortBy('score')->values();

        return response()->json($results);
    }

    /** Planning, active and on-hold projects of this workspace that the user may see (a manager: their own). */
    private function openProjects(): Collection
    {
        $user = auth()->user();

        return Project::forWorkspace($user->currentWorkspace->id)
            ->visibleTo($user)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->get();
    }
}
