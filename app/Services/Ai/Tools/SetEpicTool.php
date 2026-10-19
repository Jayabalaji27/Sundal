<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;

/**
 * Files tasks and bugs under an epic of their project, or takes them out of
 * their epic ("none"), as the Epics tab does. Can be undone.
 */
class SetEpicTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'set_epic';
    }

    public function description(): string
    {
        return 'Put tasks or bugs into an epic of their project, or take them out of their epic (epic: "none"). An item belongs to one epic at most, so putting it in moves it from any other. Shows a confirmation card listing every item.';
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
            'epic' => ['type' => 'string', 'required' => true, 'description' => 'Epic name or id, or "none" to take the items out of their epic.'],
            'items' => ['type' => 'string', 'required' => true, 'description' => 'Task or bug titles or ids, separated by ";" (e.g. "Login page; #42").'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $none = in_array(mb_strtolower(trim((string) ($args['epic'] ?? ''))), ['none', 'no epic', 'remove', ''], true);
        $epic = $none ? null : $this->resolver->epic($user, (string) $args['epic'], $args['project'] ?? null);
        $projectRef = $epic ? '#' . $epic->project_id : ($args['project'] ?? null);

        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['items'] ?? '')))));
        if (!$refs) {
            throw new ToolInputException(__('Which tasks or bugs? List their titles or ids.'));
        }
        $items = collect($refs)->map(fn (string $ref) => $this->resolver->task($user, $ref, $projectRef))->unique('id');
        $items = $items->reject(fn (Task $t) => (int) $t->epic_id === (int) $epic?->id);
        if ($items->isEmpty()) {
            throw new ToolInputException($epic
                ? __('Those items are already in epic ":name".', ['name' => $epic->name])
                : __('Those items are not in any epic.'));
        }

        $count = $items->count();

        return new PreparedAction(
            summary: $epic
                ? trans_choice('Put :count item into epic ":name"|Put :count items into epic ":name"', $count, ['count' => $count, 'name' => $epic->name])
                : trans_choice('Take :count item out of its epic|Take :count items out of their epics', $count, ['count' => $count]),
            details: array_filter([__('Epic') => $epic?->name ?? __('None'), __('Project') => $epic?->project?->title]),
            payload: ['epic_id' => $epic?->id, 'task_ids' => $items->pluck('id')->values()->all()],
            items: $items->map(fn (Task $t) => ($t->type === 'bug' ? __('Bug') . ': ' : '') . "{$t->title} (#{$t->id})")->values()->all(),
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? "MOVE {$count}" : null,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $epic = $payload['epic_id'] ? $this->resolver->byId($this->resolver->epics($user), (int) $payload['epic_id'], __('epic')) : null;
        $items = $this->resolver->tasks($user)->whereIn('tasks.id', $payload['task_ids'])
            ->when($epic, fn ($q) => $q->where('project_id', $epic->project_id))->get();
        if ($items->count() !== count($payload['task_ids'])) {
            throw new ToolInputException(__('Some items on this card no longer exist or are no longer visible to you. Nothing was changed.'));
        }

        $before = $items->mapWithKeys(fn (Task $t) => [$t->id => $t->epic_id])->all();
        // Saved one by one, so each item's history shows the change.
        $items->each->update(['epic_id' => $epic?->id]);

        return new ToolOutcome(
            $epic
                ? trans_choice('Put :count item into epic ":name".|Put :count items into epic ":name".', $items->count(), ['count' => $items->count(), 'name' => $epic->name])
                : trans_choice('Took :count item out of its epic.|Took :count items out of their epics.', $items->count(), ['count' => $items->count()]),
            $epic,
            $epic ? route('epics.index', $epic->project_id, false) : null,
            ['epic_id' => $epic?->id, 'before' => $before],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $ids = array_map('intval', array_keys($undo['before']));
        $items = $this->resolver->tasks($user)->whereIn('tasks.id', $ids)->get();
        if ($items->contains(fn (Task $t) => (int) $t->epic_id !== (int) ($undo['epic_id'] ?? 0))) {
            throw new ToolInputException(__('Some of these items were moved again since, so nothing was undone.'));
        }
        foreach ($items as $item) {
            $item->update(['epic_id' => $undo['before'][$item->id] ?? null]);
        }

        return trans_choice('Put :count item back.|Put :count items back.', count($ids), ['count' => count($ids)]);
    }
}
