<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\ActionException;
use App\Actions\Projects\CreateProject;
use App\Models\User;
use App\Models\Workspace;

class CreateProjectTool extends AiTool implements HasForm
{
    private const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateProject $createProject,
    ) {}

    public function name(): string
    {
        return 'create_project';
    }

    public function description(): string
    {
        return 'Create a project, optionally with team members and clients. Shows the user a confirmation card; nothing is created until they confirm.';
    }

    public function permissions(): array
    {
        return ['project_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Project name, at most 255 characters.'],
            'description' => ['type' => 'string', 'description' => 'What the project is about.'],
            'status' => ['type' => 'enum', 'options' => self::STATUSES, 'description' => 'Defaults to planning.'],
            'priority' => ['type' => 'enum', 'options' => self::PRIORITIES, 'description' => 'Defaults to medium.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'deadline' => ['type' => 'string', 'description' => 'YYYY-MM-DD, after the start date.'],
            'budget' => ['type' => 'number', 'description' => 'Budget amount.'],
            'members' => ['type' => 'string', 'description' => 'Team members to add: names or emails separated by ";".'],
            'clients' => ['type' => 'string', 'description' => 'Clients to add: names or emails separated by ";".'],
        ];
    }

    public function formTitle(): string
    {
        return __('New project');
    }

    public function formFields(User $user): array
    {
        return array_values(array_filter([
            new FormField('title', __('Name'), 'text', required: true, question: __('What should the project be called?')),
            new FormField('status', __('Status'), 'enum', required: true, options: FormField::labels(self::STATUSES), default: 'planning'),
            new FormField('priority', __('Priority'), 'enum', required: true, options: FormField::labels(self::PRIORITIES), default: 'medium'),
            new FormField('deadline', __('Deadline'), 'date'),
            $user->hasWorkspacePermission('project_assign_members')
                ? new FormField('members', __('Members'), 'members')
                : null,
        ]));
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        try {
            $this->createProject->ensureAllowed($user);
        } catch (ActionException $e) {
            throw new ToolInputException($e->getMessage());
        }

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The project needs a name of at most 255 characters.'));
        }

        $status = $args['status'] ?? 'planning';
        $priority = $args['priority'] ?? 'medium';
        if (!in_array($status, self::STATUSES, true) || !in_array($priority, self::PRIORITIES, true)) {
            throw new ToolInputException(__('Status must be planning, active, on_hold, completed or cancelled; priority low, medium, high or urgent.'));
        }

        $start = $this->resolver->date($args['start_date'] ?? null, __('Start date'));
        $deadline = $this->resolver->date($args['deadline'] ?? null, __('Deadline'));
        if ($start && $deadline && $deadline <= $start) {
            throw new ToolInputException(__('The deadline must be after the start date.'));
        }

        $budget = isset($args['budget']) && $args['budget'] !== '' ? (float) $args['budget'] : null;
        if ($budget !== null && $budget < 0) {
            throw new ToolInputException(__('The budget cannot be negative.'));
        }

        $members = $this->people($user, $args['members'] ?? null, 'project_assign_members');
        $clients = $this->people($user, $args['clients'] ?? null, 'project_assign_clients', 'client');

        return new PreparedAction(
            summary: __('Create project ":title"', ['title' => $title]),
            details: array_filter([
                __('Name') => $title,
                __('Status') => ucfirst(str_replace('_', ' ', $status)),
                __('Priority') => ucfirst($priority),
                __('Start') => $start,
                __('Deadline') => $deadline,
                __('Budget') => $budget !== null ? number_format($budget, 2) : null,
                __('Members') => $members->pluck('name')->implode(', ') ?: null,
                __('Clients') => $clients->pluck('name')->implode(', ') ?: null,
            ]),
            payload: array_filter([
                'title' => $title,
                'description' => isset($args['description']) ? trim((string) $args['description']) : null,
                'status' => $status,
                'priority' => $priority,
                'start_date' => $start,
                'deadline' => $deadline,
                'budget' => $budget,
                'member_ids' => $members->pluck('id')->all(),
                'client_ids' => $clients->pluck('id')->all(),
            ], fn ($v) => $v !== null && $v !== []),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        foreach ([...($payload['member_ids'] ?? []), ...($payload['client_ids'] ?? [])] as $id) {
            $this->resolver->byId($this->resolver->members($user), (int) $id, __('person'));
        }

        $project = $this->createProject->handle($user, $payload);

        return new ToolOutcome(
            __('Created project ":title".', ['title' => $project->title]),
            $project,
            route('projects.show', $project->id, false),
        );
    }

    /** People named in a ";" list, all workspace members, if the user may assign them. */
    private function people(User $user, ?string $list, string $permission, ?string $requiredRole = null)
    {
        $refs = array_values(array_filter(array_map('trim', explode(';', (string) $list))));
        if (!$refs) {
            return collect();
        }
        if (!$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('This user may create projects but not add these people. Create it without them or ask the user.'));
        }

        $workspace = Workspace::find($user->current_workspace_id);

        return collect($refs)->map(function (string $ref) use ($user, $workspace, $requiredRole) {
            $person = $this->resolver->member($user, $ref);
            if ($requiredRole && $workspace?->getMemberRole($person) !== $requiredRole) {
                throw new ToolInputException(__(':name is not a client of this workspace.', ['name' => $person->name]));
            }

            return $person;
        })->unique('id')->values();
    }
}
