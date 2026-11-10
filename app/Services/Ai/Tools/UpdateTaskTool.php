<?php

namespace App\Services\Ai\Tools;

use App\Actions\Tasks\UpdateTaskDetails;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Changes a task's details: title, description, priority, story points,
 * start and due dates, milestone. Assignee, stage, sprint and epic have
 * their own tools. UpdateBugTool adds the bug fields.
 */
class UpdateTaskTool extends AiTool
{
    use RevertsChanges;

    protected const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function __construct(
        protected readonly RecordResolver $resolver,
        protected readonly UpdateTaskDetails $updateTask,
    ) {}

    public function name(): string
    {
        return 'update_task';
    }

    public function description(): string
    {
        return 'Change a task\'s title, description, priority, story points, start or due date, or milestone. Only the values given change. For the assignee use assign_task, for the stage change_task_status, for the epic set_epic. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['task_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'task' => ['type' => 'string', 'required' => true, 'description' => 'Task title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'description' => ['type' => 'string', 'description' => 'New description (replaces the old one).'],
            'priority' => ['type' => 'enum', 'options' => self::PRIORITIES, 'description' => 'New priority.'],
            'story_points' => ['type' => 'number', 'description' => 'New story points, 0 to 100.'],
            'start_date' => ['type' => 'string', 'description' => 'New start date, YYYY-MM-DD.'],
            'due_date' => ['type' => 'string', 'description' => 'New due date, YYYY-MM-DD.'],
            'milestone' => ['type' => 'string', 'description' => 'Milestone title of the same project, or "none".'],
        ];
    }

    /** Records this tool changes; bugs are tasks too (only bugs for UpdateBugTool). */
    protected function records(User $user): Builder
    {
        return $this->resolver->tasks($user);
    }

    protected function find(User $user, array $args): Task
    {
        return $this->resolver->task($user, (string) ($args['task'] ?? ''), $args['project'] ?? null);
    }

    protected function kind(): string
    {
        return __('Task');
    }

    /** Extra fields of this kind of record (bug fields). */
    protected function extraChanges(array $args, Task $task): array
    {
        return [];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $task = $this->find($user, $args);
        $text = fn (string $key, int $max) => isset($args[$key]) && trim((string) $args[$key]) !== '' ? mb_substr(trim((string) $args[$key]), 0, $max) : null;

        $changes = [
            'title' => $text('title', 255),
            'description' => $text('description', 10000),
            'priority' => in_array($args['priority'] ?? null, static::PRIORITIES, true) ? $args['priority'] : null,
            'story_points' => isset($args['story_points']) && $args['story_points'] !== '' ? (int) $this->resolver->number($args['story_points'], __('Story points'), 100) : null,
            'start_date' => $this->resolver->date($args['start_date'] ?? null, __('Start date')),
            'end_date' => $this->resolver->date($args['due_date'] ?? null, __('Due date')),
            ...$this->extraChanges($args, $task),
        ];

        $milestoneLabel = null;
        if (isset($args['milestone']) && trim((string) $args['milestone']) !== '') {
            if (in_array(mb_strtolower(trim((string) $args['milestone'])), ['none', 'no milestone', 'remove'], true)) {
                $changes['milestone_id'] = 0; // shown as "none", stored as null
                $milestoneLabel = __('none');
            } else {
                $milestone = $this->resolver->milestone($user, (string) $args['milestone'], (string) $task->project_id);
                $changes['milestone_id'] = $milestone->id;
                $milestoneLabel = $milestone->title;
            }
        }

        $old = $this->snapshot($task, array_keys($changes));
        $changes = array_filter($changes, fn ($value, $field) => $value !== null && (string) $value !== (string) ($field === 'milestone_id' ? (int) ($old[$field] ?? 0) : ($old[$field] ?? '')), ARRAY_FILTER_USE_BOTH);
        if ($changes === []) {
            throw new ToolInputException(__('What should change on ":title"? Ask the user.', ['title' => $task->title]));
        }

        $start = $changes['start_date'] ?? $task->start_date?->format('Y-m-d');
        $end = $changes['end_date'] ?? $task->end_date?->format('Y-m-d');
        if ($start && $end && $end < $start) {
            throw new ToolInputException(__('The due date cannot be before the start date.'));
        }

        $task->loadMissing('project:id,title', 'milestone:id,title');
        $labels = ['end_date' => __('Due date'), 'milestone_id' => __('Milestone'), 'story_points' => __('Story points')];
        $long = ['description', 'steps_to_reproduce', 'expected_behavior', 'actual_behavior', 'resolution_notes'];

        return new PreparedAction(
            summary: __('Change :kind ":title"', ['kind' => mb_strtolower($this->kind()), 'title' => $task->title]),
            details: [
                $this->kind() => "{$task->title} (#{$task->id})",
                __('Project') => (string) $task->project?->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    $labels[$field] ?? __(ucfirst(str_replace('_', ' ', $field))) => match (true) {
                        $field === 'milestone_id' => ($task->milestone?->title ?? '—') . ' → ' . $milestoneLabel,
                        in_array($field, $long, true) => __('new text: :text', ['text' => mb_strimwidth((string) $value, 0, 80, '…')]),
                        default => ($old[$field] ?? '—') . ' → ' . $value,
                    },
                ])->all(),
            ],
            payload: ['task_id' => $task->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Task $task */
        $task = $this->resolver->byId($this->records($user), (int) $payload['task_id'], mb_strtolower($this->kind()));
        $changes = $payload['changes'];
        if (array_key_exists('milestone_id', $changes)) {
            $changes['milestone_id'] = $changes['milestone_id'] ?: null;
        }
        $before = $this->snapshot($task, array_keys($changes));

        $this->updateTask->handle($user, $task, $changes);

        return new ToolOutcome(
            __('Updated :kind ":title".', ['kind' => mb_strtolower($this->kind()), 'title' => $task->title]),
            $task,
            route('tasks.show', $task->id, false),
            $this->changeUndo($task, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Task $task */
        $task = $this->resolver->byId($this->records($user), (int) $undo['id'], mb_strtolower($this->kind()));
        $this->ensureUnchanged($task, $undo, "{$this->kind()} \"{$task->title}\"");

        $this->updateTask->handle($user, $task, $undo['before']);

        return __(':kind ":title" put back.', ['kind' => $this->kind(), 'title' => $task->title]);
    }
}
