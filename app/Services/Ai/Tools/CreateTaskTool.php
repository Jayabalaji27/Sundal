<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Tasks\CreateTask;
use App\Models\User;

class CreateTaskTool extends AiTool implements HasForm
{
    private const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateTask $createTask,
    ) {}

    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Create a task or story in a project. Shows the user a confirmation card; nothing is created until they confirm.';
    }

    public function permissions(): array
    {
        return ['task_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Only the name of the task itself, without the project, priority or person; at most 255 characters.'],
            'description' => ['type' => 'string', 'description' => 'Task description.'],
            'priority' => ['type' => 'enum', 'options' => self::PRIORITIES, 'description' => 'Defaults to medium.'],
            'assignee' => ['type' => 'string', 'description' => '"me" or a person\'s name or email.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, after the start date.'],
        ];
    }

    public function formTitle(): string
    {
        return __('New task');
    }

    public function formFields(User $user): array
    {
        return array_values(array_filter([
            new FormField('title', __('Title'), 'text', required: true, question: __('What should the task be called?')),
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('priority', __('Priority'), 'enum', required: true, options: FormField::labels(self::PRIORITIES), default: 'medium'),
            $user->hasWorkspacePermission('task_assign_users')
                ? new FormField('assignee', __('Assignee'), 'member', required: true, allowNone: true, default: 'none', question: __('Who should it be assigned to?'))
                : null,
            new FormField('due_date', __('Due date'), 'date'),
        ]));
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The task needs a title of at most 255 characters.'));
        }

        $description = isset($args['description']) ? trim((string) $args['description']) : null;
        if ($description !== null && mb_strlen($description) > 10000) {
            throw new ToolInputException(__('The description must be at most 10,000 characters.'));
        }

        $priority = $args['priority'] ?? 'medium';
        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new ToolInputException(__('Priority must be one of: low, medium, high, critical.'));
        }

        $assignee = null;
        if (!empty($args['assignee'])) {
            if (!$user->hasWorkspacePermission('task_assign_users')) {
                throw new ToolInputException(__('This user may create tasks but not assign them. Create it unassigned or ask the user.'));
            }
            $assignee = $this->resolver->member($user, (string) $args['assignee']);
        }

        $start = $this->resolver->date($args['start_date'] ?? null, __('Start date'));
        $due = $this->resolver->date($args['due_date'] ?? null, __('Due date'));
        if ($start && $due && $due <= $start) {
            throw new ToolInputException(__('The due date must be after the start date.'));
        }

        return new PreparedAction(
            summary: __('Create task ":title" in :project', ['title' => $title, 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Title') => $title,
                __('Priority') => ucfirst($priority),
                __('Assignee') => $assignee?->name ?? __('Unassigned'),
                __('Start') => $start,
                __('Due') => $due,
            ]),
            payload: [
                'project_id' => $project->id,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'assigned_to' => $assignee?->id,
                'start_date' => $start,
                'end_date' => $due,
            ],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        if (!empty($payload['assigned_to'])) {
            $this->resolver->byId($this->resolver->members($user), (int) $payload['assigned_to'], __('person'));
        }

        $task = $this->createTask->handle($user, [...$payload, 'project_id' => $project->id]);

        return new ToolOutcome(
            __('Created task ":title" in :project.', ['title' => $task->title, 'project' => $project->title]),
            $task,
            route('tasks.show', $task->id, false),
        );
    }
}
