<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timer\StartTimer;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Start the user's time tracker on a project, like the timer in the header. */
class StartTimerTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly StartTimer $startTimer,
    ) {}

    public function name(): string
    {
        return 'start_timer';
    }

    public function description(): string
    {
        return 'Start the user\'s timer on a project (and optionally a task). The time goes on today\'s timesheet when it is stopped with stop_timer. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_use_timer'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'task' => ['type' => 'string', 'description' => 'Task title or id in that project.'],
            'description' => ['type' => 'string', 'description' => 'What the user is working on.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Start the timer');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true, question: __('Which project are you working on?')),
            new FormField('task', __('Task'), 'task', narrowBy: 'project'),
            new FormField('description', __('Description'), 'text', maxLength: 1000),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        if ($user->timer_active) {
            throw new ToolInputException(__('A timer is already running. Stop it first with stop_timer.'));
        }

        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $task = !empty($args['task']) ? $this->resolver->task($user, (string) $args['task'], "#{$project->id}") : null;
        $description = isset($args['description']) && trim((string) $args['description']) !== '' ? trim((string) $args['description']) : null;

        return new PreparedAction(
            summary: __('Start the timer on :project', ['project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Task') => $task?->title,
                __('Description') => $description,
            ]),
            payload: ['project_id' => $project->id, 'task_id' => $task?->id, 'description' => $description],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        if (!empty($payload['task_id'])) {
            $this->resolver->byId($this->resolver->tasks($user)->where('project_id', $project->id), (int) $payload['task_id'], __('task'));
        }

        $entry = $this->startTimer->handle($user, $project, $payload['task_id'] ?? null, $payload['description'] ?? null);

        return new ToolOutcome(__('Timer started on :project.', ['project' => $project->title]), $entry);
    }
}
