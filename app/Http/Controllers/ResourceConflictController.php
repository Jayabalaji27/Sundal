<?php

namespace App\Http\Controllers;

use App\Services\ResourceConflicts;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class ResourceConflictController extends Controller
{
    use HasPermissionChecks;

    public function index(): \Inertia\Response
    {
        $this->authorizePermission('project_view_any');
        return Inertia::render('resource-conflicts/index', [
            'conflicts' => app(ResourceConflicts::class)->forUser(auth()->user()),
        ]);
    }

    public function api(): JsonResponse
    {
        $this->authorizePermission('project_view_any');
        return response()->json(app(ResourceConflicts::class)->forUser(auth()->user()));
    }
}
