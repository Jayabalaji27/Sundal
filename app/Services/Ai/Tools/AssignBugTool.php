<?php

namespace App\Services\Ai\Tools;

use App\Actions\Bugs\AssignBug;
use App\Models\User;

class AssignBugTool extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly AssignBug $assignBug,
    ) {}

    public function name(): string
    {
        return 'assign_bug';
    }

    public function description(): string
    {
        return 'Assign a bug to a person, optionally with a due date. Shows the user a confirmation card; nothing changes until they confirm.';
    }

    public function permissions(): array
    {
        return ['bug_assign'];
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
            'assignee' => ['type' => 'string', 'required' => true, 'description' => '"me" or a person\'s name or email.'],
            'due_date' => ['type' => 'string', 'description' => 'New due date, YYYY-MM-DD.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $bug = $this->resolver->bug($user, (string) ($args['bug'] ?? ''), $args['project'] ?? null);
        $assignee = $this->resolver->member($user, (string) ($args['assignee'] ?? ''));
        $due = $this->resolver->date($args['due_date'] ?? null, __('Due date'));

        if ($due && $bug->start_date && $due <= $bug->start_date->format('Y-m-d')) {
            throw new ToolInputException(__('The due date must be after the bug start date (:date).', ['date' => $bug->start_date->format('Y-m-d')]));
        }

        $bug->loadMissing('project:id,title', 'assignedTo:id,name');

        return new PreparedAction(
            summary: __('Assign bug ":title" to :name', ['title' => $bug->title, 'name' => $assignee->name]),
            details: array_filter([
                __('Bug') => "{$bug->title} (#{$bug->id})",
                __('Project') => $bug->project?->title,
                __('Currently assigned') => $bug->assignedTo?->name ?? __('Nobody'),
                __('New assignee') => $assignee->name,
                __('Due') => $due,
            ]),
            payload: ['bug_id' => $bug->id, 'assignee_id' => $assignee->id, 'due_date' => $due],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $payload['bug_id'], __('bug'));
        $assignee = $this->resolver->byId($this->resolver->members($user), (int) $payload['assignee_id'], __('person'));

        $before = ['assigned_to' => $bug->assigned_to, 'end_date' => $bug->end_date?->format('Y-m-d')];

        $this->assignBug->handle($user, $bug, $assignee, $payload['due_date'] ?? null);
        $bug->refresh();

        return new ToolOutcome(
            __('Assigned bug ":title" to :name.', ['title' => $bug->title, 'name' => $assignee->name]),
            $bug,
            route('bugs.show', $bug->id, false),
            [
                'bug_id' => $bug->id,
                'before' => $before,
                'after' => ['assigned_to' => $bug->assigned_to, 'end_date' => $bug->end_date?->format('Y-m-d')],
            ],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $bug = $this->resolver->byId($this->resolver->bugs($user), (int) $undo['bug_id'], __('bug'));

        if ((int) $bug->assigned_to !== (int) $undo['after']['assigned_to'] || $bug->end_date?->format('Y-m-d') !== $undo['after']['end_date']) {
            throw new ToolInputException(__('Bug ":title" was changed again since, so it was not undone.', ['title' => $bug->title]));
        }

        $this->assignBug->revert($user, $bug, $undo['before']['assigned_to'], $undo['before']['end_date']);

        return __('Bug ":title" assignment put back.', ['title' => $bug->title]);
    }
}
