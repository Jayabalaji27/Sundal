<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Models\Workspace;

class ListTeamMembers extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_team_members';
    }

    public function description(): string
    {
        return 'List people in the current workspace with their role. Use it to find the right person before assigning work.';
    }

    public function permissions(): array
    {
        return ['team_view', 'task_assign_users', 'bug_assign'];
    }

    public function parameters(): array
    {
        return [
            'search' => ['type' => 'string', 'description' => 'Part of a name or email.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $workspace = Workspace::find($user->current_workspace_id);

        $people = $this->resolver->members($user)
            ->when($args['search'] ?? null, fn ($q, $search) => $q->where(function ($w) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email']);

        return [
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'role' => $workspace?->getMemberRole($person) ?? ($workspace?->owner_id === $person->id ? 'owner' : null),
            ])->all(),
        ];
    }
}
