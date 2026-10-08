<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Services\Ai\AiAccess;
use App\Actions\ActionException;
use App\Actions\Projects\AssignProjectMembers;
use App\Models\User;

class AddProjectMembersTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly AssignProjectMembers $assign,
    ) {}

    public function name(): string
    {
        return 'add_project_members';
    }

    public function description(): string
    {
        return 'Add people to a project with a project role (member by default; only the company owner can add project managers). Shows a confirmation card listing everyone.';
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
            'role' => ['type' => 'enum', 'options' => ['member', 'manager'], 'description' => 'Project role, defaults to member.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Add project members');
    }

    public function formFields(User $user): array
    {
        // Only the company owner may add project managers.
        $roles = AiAccess::role($user) === 'owner' ? ['member', 'manager'] : ['member'];

        return [
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('people', __('People'), 'members', required: true),
            new FormField('role', __('Project role'), 'enum', required: true, options: FormField::labels($roles), default: 'member'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $role = $args['role'] ?? 'member';

        try {
            $allowed = $this->assign->allowedRoles($user, $project);
        } catch (ActionException $e) {
            throw new ToolInputException($e->getMessage());
        }
        if (!in_array($role, $allowed, true)) {
            throw new ToolInputException(__('This user may not give the project role ":role". Only the company owner can add project managers.', ['role' => $role]));
        }

        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['people'] ?? '')))));
        if (!$refs) {
            throw new ToolInputException(__('Who should be added? Give names or emails.'));
        }
        $people = collect($refs)->map(fn ($ref) => $this->resolver->member($user, $ref))->unique('id')->values();

        return new PreparedAction(
            summary: trans_choice('Add :count person to :project as :role|Add :count people to :project as :role', $people->count(), [
                'count' => $people->count(), 'project' => $project->title, 'role' => $role,
            ]),
            details: [__('Project') => $project->title, __('Role') => ucfirst($role)],
            payload: ['project_id' => $project->id, 'user_ids' => $people->pluck('id')->all(), 'role' => $role],
            items: $people->map(fn (User $p) => "{$p->name} ({$p->email})")->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        $people = collect($payload['user_ids'])->map(fn ($id) => $this->resolver->byId($this->resolver->members($user), (int) $id, __('person')));

        $this->assign->handle($user, $project, $people, $payload['role']);

        return new ToolOutcome(
            trans_choice('Added :count person to :project.|Added :count people to :project.', $people->count(), ['count' => $people->count(), 'project' => $project->title]),
            $project,
            route('projects.show', $project->id, false),
        );
    }
}
