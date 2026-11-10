<?php

namespace App\Services\Ai\Tools;

use App\Models\ProjectMember;
use App\Models\User;
use App\Services\Ai\AiAccess;

/**
 * Takes people off a project's team (they stay in the workspace), as the
 * project's Team tab does. Only the company owner removes project managers,
 * as only the owner adds them.
 */
class RemoveProjectMembersTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'remove_project_members';
    }

    public function description(): string
    {
        return 'Take people off a project\'s team. They stay in the workspace and keep their logged time; their tasks stay assigned. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['project_assign_members'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'people' => ['type' => 'string', 'required' => true, 'description' => 'Names or emails separated by ";".'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['people'] ?? '')))));
        if (!$refs) {
            throw new ToolInputException(__('Who should be taken off the project? Give names or emails.'));
        }

        $owner = AiAccess::role($user) === 'owner';
        $rows = collect($refs)->map(function (string $ref) use ($user, $project, $owner) {
            $person = $this->resolver->member($user, $ref);
            $row = ProjectMember::where('project_id', $project->id)->where('user_id', $person->id)->first()
                ?? throw new ToolInputException(__(':name is not on project :project.', ['name' => $person->name, 'project' => $project->title]));
            if ($row->role === 'manager' && !$owner) {
                throw new ToolInputException(__(':name is a project manager; only the company owner can take them off the project.', ['name' => $person->name]));
            }

            return [$row, $person];
        })->unique(fn ($pair) => $pair[1]->id)->values();

        return new PreparedAction(
            summary: trans_choice('Take :count person off :project|Take :count people off :project', $rows->count(), ['count' => $rows->count(), 'project' => $project->title]),
            details: [__('Project') => $project->title],
            payload: ['project_id' => $project->id, 'user_ids' => $rows->map(fn ($pair) => $pair[1]->id)->all()],
            items: $rows->map(fn ($pair) => "{$pair[1]->name} ({$pair[0]->role})")->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        $rows = ProjectMember::with('user:id,name')->where('project_id', $project->id)->whereIn('user_id', $payload['user_ids'])->get();
        $owner = AiAccess::role($user) === 'owner';
        if (!$owner && $rows->contains('role', 'manager')) {
            throw new ToolInputException(__('Only the company owner can take project managers off a project.'));
        }

        $kept = $rows->map(fn (ProjectMember $m) => ['user_id' => $m->user_id, 'role' => $m->role, 'assigned_by' => $m->assigned_by])->values()->all();
        $rows->each->delete();
        $project->logActivity('member_removed', "Users '" . $rows->pluck('user.name')->implode(', ') . "' were removed from project", [], $user->id);

        return new ToolOutcome(
            trans_choice('Took :count person off :project.|Took :count people off :project.', $rows->count(), ['count' => $rows->count(), 'project' => $project->title]),
            $project,
            route('projects.show', ['project' => $project->id, 'tab' => 'team'], false),
            ['project_id' => $project->id, 'members' => $kept],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $undo['project_id'], __('project'));
        foreach ($undo['members'] as $member) {
            ProjectMember::firstOrCreate(
                ['project_id' => $project->id, 'user_id' => $member['user_id']],
                ['role' => $member['role'], 'assigned_by' => $member['assigned_by']],
            );
        }
        $project->logActivity('members_assigned', 'Removed members were put back on the project', [], $user->id);

        return __('Put them back on :project.', ['project' => $project->title]);
    }
}
