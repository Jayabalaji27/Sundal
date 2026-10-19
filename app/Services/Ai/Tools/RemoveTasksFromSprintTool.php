<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;

/** Sends items of a sprint back to the backlog, as the Backlog page's ↩ button does. Can be undone. */
class RemoveTasksFromSprintTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'remove_tasks_from_sprint';
    }

    public function description(): string
    {
        return 'Take tasks or bugs out of a sprint and back to the project backlog (unplanned work). Shows a confirmation card listing every item.';
    }

    public function permissions(): array
    {
        return ['sprint_manage_tasks'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'sprint' => ['type' => 'string', 'required' => true, 'description' => 'Sprint name or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, when the sprint name repeats across projects.'],
            'tasks' => ['type' => 'string', 'required' => true, 'description' => 'Task or bug titles or ids, separated by ";".'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $sprint = $this->resolver->sprint($user, (string) ($args['sprint'] ?? ''), $args['project'] ?? null);
        if ($sprint->status === 'completed') {
            throw new ToolInputException(__('Sprint ":name" is completed; its items cannot be moved.', ['name' => $sprint->name]));
        }

        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['tasks'] ?? '')))));
        if (!$refs) {
            throw new ToolInputException(__('Which tasks? List their titles or ids.'));
        }
        $inSprint = $sprint->tasks()->pluck('tasks.id')->all();
        $tasks = collect($refs)->map(fn (string $ref) => $this->resolver->task($user, $ref, '#' . $sprint->project_id))->unique('id')
            ->filter(fn (Task $t) => in_array($t->id, $inSprint, true));
        if ($tasks->isEmpty()) {
            throw new ToolInputException(__('None of those items is in sprint ":name".', ['name' => $sprint->name]));
        }

        $count = $tasks->count();

        return new PreparedAction(
            summary: trans_choice('Move :count item from ":name" back to the backlog|Move :count items from ":name" back to the backlog', $count, ['count' => $count, 'name' => $sprint->name]),
            details: [__('Sprint') => "{$sprint->name} ({$sprint->status})", __('Project') => (string) $sprint->project?->title],
            payload: ['sprint_id' => $sprint->id, 'task_ids' => $tasks->pluck('id')->values()->all()],
            items: $tasks->map(fn (Task $t) => "{$t->title} (#{$t->id})")->values()->all(),
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? "MOVE {$count}" : null,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $payload['sprint_id'], __('sprint'));
        $sprint->tasks()->detach($payload['task_ids']);
        $count = count($payload['task_ids']);

        return new ToolOutcome(
            trans_choice('Moved :count item back to the backlog.|Moved :count items back to the backlog.', $count, ['count' => $count]),
            $sprint,
            route('projects.backlog', $sprint->project_id, false),
            ['sprint_id' => $sprint->id, 'task_ids' => $payload['task_ids']],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $undo['sprint_id'], __('sprint'));
        // Only items still unplanned go back: one picked into another sprint since stays there.
        $elsewhere = \DB::table('sprint_tasks')->join('sprints', 'sprints.id', '=', 'sprint_tasks.sprint_id')
            ->whereIn('sprint_tasks.task_id', $undo['task_ids'])->whereIn('sprints.status', ['active', 'planning'])
            ->pluck('sprint_tasks.task_id')->all();
        $back = array_values(array_diff($undo['task_ids'], $elsewhere));
        if (!$back) {
            throw new ToolInputException(__('These items were planned into another sprint since, so nothing was undone.'));
        }
        $sprint->tasks()->syncWithoutDetaching($back);

        return trans_choice('Put :count item back into ":name".|Put :count items back into ":name".', count($back), ['count' => count($back), 'name' => $sprint->name]);
    }
}
