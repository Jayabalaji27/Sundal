<?php

namespace App\Actions\Projects;

use App\Actions\ActionException;
use App\Events\ProjectCreated;
use App\Models\Project;
use App\Models\ProjectClient;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project in the actor's workspace, after the plan's project limit.
 * Shared by ProjectController and the AI assistant. Expects validated data;
 * optional `member_ids` and `client_ids` are attached with the actor as
 * assigned_by.
 */
class CreateProject
{
    public function __construct(private readonly PlanLimitService $planLimits) {}

    /** @throws ActionException when the plan allows no more projects */
    public function ensureAllowed(User $actor): void
    {
        $workspace = Workspace::find($actor->current_workspace_id)
            ?? throw new ActionException(__('No workspace found. Please select a workspace.'));

        $check = $this->planLimits->canCreateProject($workspace);
        if (!$check['allowed']) {
            throw new ActionException($check['message']);
        }
    }

    public function handle(User $actor, array $data): Project
    {
        $this->ensureAllowed($actor);

        $clientIds = $data['client_ids'] ?? [];
        $memberIds = $data['member_ids'] ?? [];

        $project = DB::transaction(function () use ($actor, $data, $clientIds, $memberIds) {
            $project = Project::create([
                ...collect($data)->except(['client_ids', 'member_ids'])->all(),
                'workspace_id' => $actor->current_workspace_id,
                'created_by' => $actor->id,
                'budget' => $data['budget'] ?? 0,
                'estimated_hours' => $data['estimated_hours'] ?? 0,
            ]);

            foreach ($clientIds as $clientId) {
                ProjectClient::create(['project_id' => $project->id, 'user_id' => $clientId, 'assigned_by' => $actor->id]);
            }

            foreach ($memberIds as $userId) {
                ProjectMember::create(['project_id' => $project->id, 'user_id' => $userId, 'role' => 'member', 'assigned_by' => $actor->id]);
            }

            $project->logActivity('created', "Project '{$project->title}' was created", [], $actor->id);

            return $project;
        });

        if (!config('app.is_demo', true)) {
            event(new ProjectCreated($project));
        }

        return $project;
    }
}
