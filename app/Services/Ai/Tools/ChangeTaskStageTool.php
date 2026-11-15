<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Tasks\ChangeTaskStage;
use App\Models\TaskStage;
use App\Models\User;

class ChangeTaskStageTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ChangeTaskStage $changeStage,
    ) {}

    public function name(): string
    {
        return 'change_task_status';
    }

    public function description(): string
    {
        return 'Move a task or story to another stage (status), e.g. "In Progress" or "Done". Shows the user a confirmation card; nothing changes until they confirm.';
    }

    public function permissions(): array
    {
        return ['task_change_status'];
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
            'stage' => ['type' => 'string', 'required' => true, 'description' => 'Target stage name.'],
            'reason' => ['type' => 'string', 'description' => 'Why it moves back. Required when the target stage comes before the current one; ask the user, never invent it.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Move a task');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('task', __('Task'), 'task', required: true, narrowBy: 'project'),
            new FormField('stage', __('Stage'), 'task_stage', required: true, question: __('Which stage should it move to?')),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $task = $this->resolver->task($user, (string) ($args['task'] ?? ''), $args['project'] ?? null);
        $stage = $this->resolver->taskStage($user, (string) ($args['stage'] ?? ''));
        $task->loadMissing('project:id,title', 'taskStage:id,name');

        if ($task->task_stage_id === $stage->id) {
            throw new ToolInputException(__('Task ":title" is already in :stage.', ['title' => $task->title, 'stage' => $stage->name]));
        }

        // The process rules (unassigned, reopen, reason for moving back) before the card.
        try {
            $this->changeStage->check($user, $task, $task->taskStage, $stage, $args['reason'] ?? null);
        } catch (\App\Actions\Tasks\StageMoveRefused $e) {
            throw new ToolInputException($e->getMessage() . ($e->needsReason ? ' ' . __('Ask the user for the reason.') : ''));
        }

        return new PreparedAction(
            summary: __('Move task ":title" to :stage', ['title' => $task->title, 'stage' => $stage->name]),
            details: array_filter([
                __('Task') => "{$task->title} (#{$task->id})",
                __('Project') => $task->project?->title,
                __('From') => $task->taskStage?->name,
                __('To') => $stage->name,
                __('Reason') => $args['reason'] ?? null,
            ]),
            payload: ['task_id' => $task->id, 'stage_id' => $stage->id, 'reason' => $args['reason'] ?? null],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $payload['task_id'], __('task'));
        $stage = $this->resolver->byId(TaskStage::forWorkspace($user->current_workspace_id), (int) $payload['stage_id'], __('task stage'));

        $fromStageId = $task->task_stage_id;
        try {
            $this->changeStage->handle($user, $task, $stage, $payload['reason'] ?? null);
        } catch (\App\Actions\Tasks\StageMoveRefused $e) {
            throw new ToolInputException($e->getMessage());
        }

        return new ToolOutcome(
            __('Moved task ":title" to :stage.', ['title' => $task->title, 'stage' => $stage->name]),
            $task,
            route('tasks.show', $task->id, false),
            ['task_id' => $task->id, 'from_stage_id' => $fromStageId, 'to_stage_id' => $stage->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $undo['task_id'], __('task'));
        if ((int) $task->task_stage_id !== (int) $undo['to_stage_id']) {
            throw new ToolInputException(__('Task ":title" was moved again since, so it was not undone.', ['title' => $task->title]));
        }

        $stage = $this->resolver->byId(TaskStage::forWorkspace($user->current_workspace_id), (int) $undo['from_stage_id'], __('task stage'));
        $this->changeStage->handle($user, $task, $stage, enforceRules: false); // undoing one's own move

        return __('Task ":title" moved back to :stage.', ['title' => $task->title, 'stage' => $stage->name]);
    }
}
