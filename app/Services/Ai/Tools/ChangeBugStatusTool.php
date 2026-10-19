<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Tasks\ChangeTaskStage;
use App\Models\TaskStage;
use App\Models\User;

/**
 * Bugs are tasks of type bug and move through the same stages as tasks.
 * Bug words ("Resolved", "Closed", "Fixed") are understood as the done stage.
 */
class ChangeBugStatusTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ChangeTaskStage $changeStage,
    ) {}

    public function name(): string
    {
        return 'change_bug_status';
    }

    public function description(): string
    {
        return 'Move a bug to another stage, e.g. "In Progress" or "Done" ("Resolved" and "Closed" mean Done). Shows the user a confirmation card; nothing changes until they confirm.';
    }

    public function permissions(): array
    {
        return ['bug_change_status'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'bug' => ['type' => 'string', 'required' => true, 'description' => 'Bug title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the bug search.'],
            'status' => ['type' => 'string', 'required' => true, 'description' => 'Target stage name.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Change bug status');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('bug', __('Bug'), 'bug', required: true, narrowBy: 'project'),
            new FormField('status', __('Status'), 'task_stage', required: true, question: __('Which stage should it move to?')),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $bug = $this->resolver->bug($user, (string) ($args['bug'] ?? ''), $args['project'] ?? null);
        $stage = $this->resolver->bugStage($user, (string) ($args['status'] ?? ''));
        $bug->loadMissing('project:id,title', 'taskStage:id,name');

        if ($bug->task_stage_id === $stage->id) {
            throw new ToolInputException(__('Bug ":title" is already in :status.', ['title' => $bug->title, 'status' => $stage->name]));
        }

        return new PreparedAction(
            summary: __('Move bug ":title" to :status', ['title' => $bug->title, 'status' => $stage->name]),
            details: array_filter([
                __('Bug') => "{$bug->title} (#{$bug->id})",
                __('Project') => $bug->project?->title,
                __('From') => $bug->taskStage?->name,
                __('To') => $stage->name,
            ]),
            payload: ['bug_id' => $bug->id, 'stage_id' => $stage->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $payload['bug_id'], __('bug'));
        $stage = $this->resolver->byId(TaskStage::forWorkspace($user->current_workspace_id), (int) $payload['stage_id'], __('task stage'));
        $from = $bug->task_stage_id;

        $this->changeStage->handle($user, $bug, $stage);

        return new ToolOutcome(
            __('Moved bug ":title" to :status.', ['title' => $bug->title, 'status' => $stage->name]),
            $bug,
            route('tasks.show', $bug->id, false),
            ['bug_id' => $bug->id, 'from_stage_id' => $from, 'to_stage_id' => $stage->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $undo['bug_id'], __('bug'));
        if ((int) $bug->task_stage_id !== (int) $undo['to_stage_id']) {
            throw new ToolInputException(__('Bug ":title" was moved again since, so it was not undone.', ['title' => $bug->title]));
        }

        $stage = $this->resolver->byId(TaskStage::forWorkspace($user->current_workspace_id), (int) $undo['from_stage_id'], __('task stage'));
        $this->changeStage->handle($user, $bug, $stage);

        return __('Bug ":title" moved back to :status.', ['title' => $bug->title, 'status' => $stage->name]);
    }
}
