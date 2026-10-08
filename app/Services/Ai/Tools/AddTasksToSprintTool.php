<?php

namespace App\Services\Ai\Tools;

use App\Actions\Sprints\AddTasksToSprint;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;

/**
 * Sprints themselves are not created in Sundal any more (the sprints.store
 * route was removed on purpose), so the assistant can only fill existing ones.
 */
class AddTasksToSprintTool extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly AddTasksToSprint $addTasks,
    ) {}

    public function name(): string
    {
        return 'add_tasks_to_sprint';
    }

    public function description(): string
    {
        return 'Add one or more tasks of a project to one of its existing sprints. Sundal does not create new sprints. Shows a confirmation card listing every task.';
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
            'tasks' => ['type' => 'string', 'required' => true, 'description' => 'Task titles or ids, separated by ";" (e.g. "Login page; #42").'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $sprint = $this->sprint($user, (string) ($args['sprint'] ?? ''));

        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['tasks'] ?? '')))));
        if (!$refs) {
            throw new ToolInputException(__('Which tasks? List their titles or ids.'));
        }

        $tasks = collect($refs)->map(fn (string $ref) => $this->resolver->task($user, $ref, '#' . $sprint->project_id))->unique('id');
        $already = $sprint->tasks()->whereIn('tasks.id', $tasks->pluck('id'))->pluck('tasks.id')->all();
        $tasks = $tasks->reject(fn (Task $t) => in_array($t->id, $already, true));

        if ($tasks->isEmpty()) {
            throw new ToolInputException(__('Those tasks are already in sprint ":name".', ['name' => $sprint->name]));
        }

        $count = $tasks->count();

        return new PreparedAction(
            summary: trans_choice('Add :count task to sprint ":name"|Add :count tasks to sprint ":name"', $count, ['count' => $count, 'name' => $sprint->name]),
            details: [__('Sprint') => "{$sprint->name} ({$sprint->status})", __('Project') => (string) $sprint->project?->title],
            payload: ['sprint_id' => $sprint->id, 'task_ids' => $tasks->pluck('id')->values()->all()],
            items: $tasks->map(fn (Task $t) => "{$t->title} (#{$t->id})")->values()->all(),
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? "ADD {$count}" : null,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $sprint = $this->resolver->byId($this->sprints($user), (int) $payload['sprint_id'], __('sprint'));
        $tasks = $this->resolver->tasks($user)->whereIn('tasks.id', $payload['task_ids'])->get();
        if ($tasks->count() !== count($payload['task_ids'])) {
            throw new ToolInputException(__('Some tasks on this card no longer exist or are no longer visible to you. Nothing was changed.'));
        }

        $added = $this->addTasks->handle($user, $sprint, $tasks);

        return new ToolOutcome(
            trans_choice('Added :count task to sprint ":name".|Added :count tasks to sprint ":name".', $added, ['count' => $added, 'name' => $sprint->name]),
            $sprint,
            route('sprints.show', $sprint->id, false),
        );
    }

    private function sprints(User $user)
    {
        return Sprint::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->whereIn('project_id', $this->resolver->projects($user)->select('id'))
            ->with('project:id,title');
    }

    private function sprint(User $user, string $ref): Sprint
    {
        $ref = trim($ref);
        if (preg_match('/^#?(\d+)$/', $ref, $m) && ($byId = $this->sprints($user)->whereKey((int) $m[1])->first())) {
            return $byId;
        }

        $matches = $this->sprints($user)->where('name', 'like', '%' . addcslashes($ref, '%_\\') . '%')->limit(6)->get();
        $exact = $matches->filter(fn (Sprint $s) => mb_strtolower($s->name) === mb_strtolower($ref));

        return match (true) {
            $exact->count() === 1 => $exact->first(),
            $matches->count() === 1 => $matches->first(),
            $matches->isEmpty() => throw new ToolInputException(__('No sprint matches ":ref". Sundal does not create new sprints; ask the user for an existing one.', ['ref' => $ref])),
            default => throw new ToolInputException(__('Several sprints match ":ref": :list. Ask the user which one; do not guess.', [
                'ref' => $ref,
                'list' => $matches->map(fn (Sprint $s) => "{$s->name} in {$s->project?->title} (#{$s->id})")->implode('; '),
            ])),
        };
    }
}
