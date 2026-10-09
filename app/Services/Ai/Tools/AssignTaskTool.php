<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Tasks\AssignTask;
use App\Models\User;

class AssignTaskTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly AssignTask $assignTask,
    ) {}

    public function name(): string
    {
        return 'assign_task';
    }

    public function description(): string
    {
        return 'Assign a task or story to a person, optionally with a due date. Shows the user a confirmation card; nothing changes until they confirm.';
    }

    public function permissions(): array
    {
        return ['task_assign_users'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'task' => ['type' => 'string', 'required' => true, 'description' => 'Task title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the task search.'],
            'assignee' => ['type' => 'string', 'required' => true, 'description' => '"me" or a person\'s name or email.'],
            'due_date' => ['type' => 'string', 'description' => 'New due date, YYYY-MM-DD.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Assign a task');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('task', __('Task'), 'task', required: true, narrowBy: 'project'),
            new FormField('assignee', __('Assignee'), 'member', required: true, question: __('Who should it be assigned to?')),
            new FormField('due_date', __('Due date'), 'date'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $task = $this->resolver->task($user, (string) ($args['task'] ?? ''), $args['project'] ?? null);
        $assignee = $this->resolver->member($user, (string) ($args['assignee'] ?? ''));
        $due = $this->resolver->date($args['due_date'] ?? null, __('Due date'));

        if ($due && $task->start_date && $due <= $task->start_date->format('Y-m-d')) {
            throw new ToolInputException(__('The due date must be after the task start date (:date).', ['date' => $task->start_date->format('Y-m-d')]));
        }

        $task->loadMissing('project:id,title', 'assignedTo:id,name');

        return new PreparedAction(
            summary: __('Assign task ":title" to :name', ['title' => $task->title, 'name' => $assignee->name]),
            details: array_filter([
                __('Task') => "{$task->title} (#{$task->id})",
                __('Project') => $task->project?->title,
                __('Currently assigned') => $task->assignedTo?->name ?? __('Nobody'),
                __('New assignee') => $assignee->name,
                __('Due') => $due,
            ]),
            payload: ['task_id' => $task->id, 'assignee_id' => $assignee->id, 'due_date' => $due],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $payload['task_id'], __('task'));
        $assignee = $this->resolver->byId($this->resolver->members($user), (int) $payload['assignee_id'], __('person'));

        $before = ['assigned_to' => $task->assigned_to, 'end_date' => $task->end_date?->format('Y-m-d')];

        $this->assignTask->handle($user, $task, $assignee, $payload['due_date'] ?? null);
        $task->refresh();

        return new ToolOutcome(
            __('Assigned task ":title" to :name.', ['title' => $task->title, 'name' => $assignee->name]),
            $task,
            route('tasks.show', $task->id, false),
            [
                'task_id' => $task->id,
                'before' => $before,
                'after' => ['assigned_to' => $task->assigned_to, 'end_date' => $task->end_date?->format('Y-m-d')],
            ],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $undo['task_id'], __('task'));

        if ((int) $task->assigned_to !== (int) $undo['after']['assigned_to'] || $task->end_date?->format('Y-m-d') !== $undo['after']['end_date']) {
            throw new ToolInputException(__('Task ":title" was changed again since, so it was not undone.', ['title' => $task->title]));
        }

        $this->assignTask->revert($user, $task, $undo['before']['assigned_to'], $undo['before']['end_date']);

        return __('Task ":title" assignment put back.', ['title' => $task->title]);
    }
}
