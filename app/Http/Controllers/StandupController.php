<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\StandupReport;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StandupController extends Controller
{
    use HasPermissionChecks;

    public function index(Request $request)
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) abort(404);

        $date     = $request->input('date', now()->toDateString());
        $standups = app(StandupReport::class)->forUser($user, $date);

        $projects = Project::forWorkspace($workspace->id)
            ->visibleTo($user)
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        return Inertia::render('standup/index', [
            'standups'  => $standups,
            'date'      => $date,
            'projects'  => $projects,
        ]);
    }

    public function api(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizePermission('project_view_any');

        $user      = auth()->user();
        $workspace = $user->currentWorkspace;
        if (!$workspace) abort(404);

        $date     = $request->input('date', now()->toDateString());
        $standups = app(StandupReport::class)->forUser($user, $date);

        return response()->json(['standups' => $standups, 'date' => $date]);
    }
}
