<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\User;

/**
 * Adds checklist items to a task or bug, or ticks existing ones off, as the
 * task's Checklist does. The task's progress follows its checklist.
 */
class UpdateChecklistTool extends AiTool
{
    private const MAX_ITEMS = 30;

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'update_checklist';
    }

    public function description(): string
    {
        return 'Add checklist items (sub-steps) to a task or bug, and/or tick existing items done or not done. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['task_manage_checklists'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'task' => ['type' => 'string', 'required' => true, 'description' => 'Task or bug title or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'add' => ['type' => 'string', 'description' => 'New items, one per line.'],
            'mark_done' => ['type' => 'string', 'description' => 'Existing item titles to tick done, one per line.'],
            'mark_not_done' => ['type' => 'string', 'description' => 'Existing item titles to untick, one per line.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $task = $this->resolver->task($user, (string) ($args['task'] ?? ''), $args['project'] ?? null);
        $lines = fn (string $key) => collect(preg_split('/\r?\n|;/', (string) ($args[$key] ?? '')))
            ->map(fn ($l) => trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $l)))
            ->filter()->unique()->values();

        $add = $lines('add');
        if ($add->count() > self::MAX_ITEMS) {
            throw new ToolInputException(__('Add at most :max checklist items at a time.', ['max' => self::MAX_ITEMS]));
        }
        if ($long = $add->first(fn ($l) => mb_strlen($l) > 255)) {
            throw new ToolInputException(__('Checklist item ":item" is longer than 255 characters.', ['item' => mb_strimwidth($long, 0, 40, '…')]));
        }

        $existing = $task->checklists()->get(['id', 'title', 'is_completed']);
        $find = function (string $title) use ($existing, $task) {
            $matches = $existing->filter(fn ($c) => mb_strtolower($c->title) === mb_strtolower($title));
            if ($matches->isEmpty()) {
                $matches = $existing->filter(fn ($c) => str_contains(mb_strtolower($c->title), mb_strtolower($title)));
            }
            if ($matches->count() !== 1) {
                throw new ToolInputException(__(':count checklist items of ":task" match ":item". Its items are: :list. Ask the user which one.', [
                    'count' => $matches->count(), 'task' => $task->title, 'item' => $title,
                    'list' => $existing->pluck('title')->implode('; ') ?: __('none'),
                ]));
            }

            return $matches->first();
        };
        $done = $lines('mark_done')->map($find)->reject(fn ($c) => $c->is_completed);
        $notDone = $lines('mark_not_done')->map($find)->filter(fn ($c) => $c->is_completed);

        if ($add->isEmpty() && $done->isEmpty() && $notDone->isEmpty()) {
            throw new ToolInputException(__('Nothing to change on the checklist of ":title". Ask the user what to add or tick.', ['title' => $task->title]));
        }
        $task->loadMissing('project:id,title');

        return new PreparedAction(
            summary: __('Update the checklist of ":title"', ['title' => $task->title]),
            details: array_filter([
                ($task->isBug() ? __('Bug') : __('Task')) => "{$task->title} (#{$task->id})",
                __('Project') => (string) $task->project?->title,
                __('Add') => $add->isNotEmpty() ? (string) $add->count() : null,
                __('Tick done') => $done->isNotEmpty() ? $done->pluck('title')->implode(', ') : null,
                __('Untick') => $notDone->isNotEmpty() ? $notDone->pluck('title')->implode(', ') : null,
            ]),
            payload: ['task_id' => $task->id, 'add' => $add->all(), 'done' => $done->pluck('id')->values()->all(), 'not_done' => $notDone->pluck('id')->values()->all()],
            items: $add->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Task $task */
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $payload['task_id'], __('task'));

        $order = (int) ($task->checklists()->max('order') ?? 0);
        $added = collect($payload['add'])->map(fn (string $title) => TaskChecklist::create([
            'task_id' => $task->id,
            'title' => $title,
            'order' => ++$order,
            'is_completed' => false,
            'created_by' => $user->id,
        ])->id);
        $task->checklists()->whereKey($payload['done'])->update(['is_completed' => true]);
        $task->checklists()->whereKey($payload['not_done'])->update(['is_completed' => false]);

        $task->syncProgressFromChecklists();

        return new ToolOutcome(
            __('Updated the checklist of ":title".', ['title' => $task->title]),
            $task,
            route('tasks.show', $task->id, false),
            ['task_id' => $task->id, 'added' => $added->all(), 'done' => $payload['done'], 'not_done' => $payload['not_done']],
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Task $task */
        $task = $this->resolver->byId($this->resolver->tasks($user), (int) $undo['task_id'], __('task'));

        $task->checklists()->whereKey($undo['added'])->delete();
        $task->checklists()->whereKey($undo['done'])->update(['is_completed' => false]);
        $task->checklists()->whereKey($undo['not_done'])->update(['is_completed' => true]);
        $task->syncProgressFromChecklists();

        return __('Checklist of ":title" put back.', ['title' => $task->title]);
    }
}
