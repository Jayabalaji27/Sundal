<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;
use App\Actions\Bugs\ChangeBugStatus;
use App\Models\BugStatus;
use App\Models\User;

class ChangeBugStatusTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ChangeBugStatus $changeStatus,
    ) {}

    public function name(): string
    {
        return 'change_bug_status';
    }

    public function description(): string
    {
        return 'Move a bug to another status, e.g. "In Progress", "Resolved" or "Closed". Shows the user a confirmation card; nothing changes until they confirm.';
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
            'status' => ['type' => 'string', 'required' => true, 'description' => 'Target status name.'],
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
            new FormField('status', __('Status'), 'bug_status', required: true),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $bug = $this->resolver->bug($user, (string) ($args['bug'] ?? ''), $args['project'] ?? null);
        $status = $this->resolver->bugStatus($user, (string) ($args['status'] ?? ''));
        $bug->loadMissing('project:id,title', 'bugStatus:id,name');

        if ($bug->bug_status_id === $status->id) {
            throw new ToolInputException(__('Bug ":title" is already :status.', ['title' => $bug->title, 'status' => $status->name]));
        }

        return new PreparedAction(
            summary: __('Move bug ":title" to :status', ['title' => $bug->title, 'status' => $status->name]),
            details: array_filter([
                __('Bug') => "{$bug->title} (#{$bug->id})",
                __('Project') => $bug->project?->title,
                __('From') => $bug->bugStatus?->name,
                __('To') => $status->name,
            ]),
            payload: ['bug_id' => $bug->id, 'status_id' => $status->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $payload['bug_id'], __('bug'));
        $status = $this->resolver->byId(BugStatus::forWorkspace($user->current_workspace_id), (int) $payload['status_id'], __('bug status'));
        $from = $bug->bug_status_id;

        $this->changeStatus->handle($user, $bug, $status);

        return new ToolOutcome(
            __('Moved bug ":title" to :status.', ['title' => $bug->title, 'status' => $status->name]),
            $bug,
            route('bugs.show', $bug->id, false),
            ['bug_id' => $bug->id, 'from_status_id' => $from, 'to_status_id' => $status->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $undo['bug_id'], __('bug'));
        if ((int) $bug->bug_status_id !== (int) $undo['to_status_id']) {
            throw new ToolInputException(__('Bug ":title" was moved again since, so it was not undone.', ['title' => $bug->title]));
        }

        $status = $this->resolver->byId(BugStatus::forWorkspace($user->current_workspace_id), (int) $undo['from_status_id'], __('bug status'));
        $this->changeStatus->handle($user, $bug, $status);

        return __('Bug ":title" moved back to :status.', ['title' => $bug->title, 'status' => $status->name]);
    }
}
