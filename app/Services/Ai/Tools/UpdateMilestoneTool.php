<?php

namespace App\Services\Ai\Tools;

use App\Actions\Projects\UpdateMilestone;
use App\Models\ProjectMilestone;
use App\Models\User;

/** Changes a milestone's title, due date, status or description. */
class UpdateMilestoneTool extends AiTool
{
    use RevertsChanges;

    private const STATUSES = ['pending', 'in_progress', 'completed', 'overdue'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly UpdateMilestone $updateMilestone,
    ) {}

    public function name(): string
    {
        return 'update_milestone';
    }

    public function description(): string
    {
        return 'Change a project milestone: rename it, move its due date, mark it in progress or completed, or change its description. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['project_manage_milestones'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'milestone' => ['type' => 'string', 'required' => true, 'description' => 'Milestone title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'due_date' => ['type' => 'string', 'description' => 'New due date, YYYY-MM-DD.'],
            'status' => ['type' => 'enum', 'options' => self::STATUSES, 'description' => 'New status.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $milestone = $this->resolver->milestone($user, (string) ($args['milestone'] ?? ''), $args['project'] ?? null);

        $changes = array_filter([
            'title' => isset($args['title']) ? trim((string) $args['title']) : null,
            'due_date' => $this->resolver->date($args['due_date'] ?? null, __('Due date')),
            'status' => in_array($args['status'] ?? null, self::STATUSES, true) ? $args['status'] : null,
            'description' => isset($args['description']) ? mb_substr(trim((string) $args['description']), 0, 5000) : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (isset($changes['title']) && mb_strlen($changes['title']) > 255) {
            throw new ToolInputException(__('The milestone needs a title of at most 255 characters.'));
        }
        $old = $this->snapshot($milestone, array_keys($changes));
        $changes = array_filter($changes, fn ($value, $field) => (string) $value !== (string) ($old[$field] ?? ''), ARRAY_FILTER_USE_BOTH);
        if ($changes === []) {
            throw new ToolInputException(__('What should change on milestone ":title"? Ask the user.', ['title' => $milestone->title]));
        }

        return new PreparedAction(
            summary: __('Change milestone ":title"', ['title' => $milestone->title]),
            details: [
                __('Milestone') => $milestone->title,
                __('Project') => $milestone->project?->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    __(ucfirst(str_replace('_', ' ', $field))) => ($field === 'description' ? '…' : ($old[$field] ?? '—')) . ' → ' . ($field === 'description' ? __('new text') : $value),
                ])->all(),
            ],
            payload: ['milestone_id' => $milestone->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var ProjectMilestone $milestone */
        $milestone = $this->resolver->byId($this->resolver->milestones($user), (int) $payload['milestone_id'], __('milestone'));
        $before = $this->snapshot($milestone, array_keys($payload['changes']));

        $this->updateMilestone->handle($user, $milestone, $payload['changes']);

        return new ToolOutcome(
            __('Updated milestone ":title".', ['title' => $milestone->title]),
            $milestone,
            route('projects.show', ['project' => $milestone->project_id, 'tab' => 'milestones'], false),
            $this->changeUndo($milestone, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var ProjectMilestone $milestone */
        $milestone = $this->resolver->byId($this->resolver->milestones($user), (int) $undo['id'], __('milestone'));
        $this->ensureUnchanged($milestone, $undo, __('Milestone ":title"', ['title' => $milestone->title]));

        $this->updateMilestone->handle($user, $milestone, $undo['before']);

        return __('Milestone ":title" put back.', ['title' => $milestone->title]);
    }
}
