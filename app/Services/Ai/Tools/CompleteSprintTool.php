<?php

namespace App\Services\Ai\Tools;

use App\Actions\Sprints\CompleteSprint;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;

/**
 * Completes the active sprint, as the Complete button on the Sprints page
 * does. Cannot be undone: the sprint's committed and finished counts are
 * frozen and its unfinished tasks leave it.
 */
class CompleteSprintTool extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CompleteSprint $completeSprint,
    ) {}

    public function name(): string
    {
        return 'complete_sprint';
    }

    public function description(): string
    {
        return 'Complete the active sprint of a project. Unfinished tasks go back to the backlog (default), to a planned sprint, or to a new sprint. Cannot be undone. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['sprint_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'sprint' => ['type' => 'string', 'description' => 'Sprint name or id. Leave out to use the active sprint of the project.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'move_unfinished_to' => ['type' => 'string', 'description' => '"backlog" (default), "new" for a new sprint, or the name of a planned sprint of the same project.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $sprint = $this->sprint($args, $user);
        if ($sprint->status !== 'active') {
            throw new ToolInputException(__('Only the active sprint can be completed; :name is :status.', ['name' => $sprint->name, 'status' => $sprint->status]));
        }

        $moveTo = mb_strtolower(trim((string) ($args['move_unfinished_to'] ?? ''))) ?: 'backlog';
        $target = null;
        if (!in_array($moveTo, ['backlog', 'new'], true)) {
            $target = $this->resolver->sprint($user, (string) $args['move_unfinished_to'], (string) $sprint->project_id);
            if ($target->status !== 'planning' || (int) $target->project_id !== (int) $sprint->project_id) {
                throw new ToolInputException(__('Choose a planned sprint of this project for the unfinished tasks.'));
            }
        }

        $tasks = $sprint->tasks()->with('taskStage:id,is_completed')->get();
        $unfinished = $tasks->reject(fn (Task $task) => $task->isCompleted());

        return new PreparedAction(
            summary: __('Complete :name in :project', ['name' => $sprint->name, 'project' => $sprint->project?->title]),
            details: [
                __('Sprint') => $sprint->name,
                __('Project') => (string) $sprint->project?->title,
                __('Finished') => (string) ($tasks->count() - $unfinished->count()),
                __('Unfinished') => (string) $unfinished->count(),
                __('Unfinished go to') => $target ? $target->name : ($moveTo === 'new' ? __('a new sprint (:name)', ['name' => CompleteSprint::nextName($sprint->project_id)]) : __('the backlog')),
            ],
            payload: ['sprint_id' => $sprint->id, 'move_to' => $target ? $target->id : $moveTo],
            items: $unfinished->map(fn (Task $t) => "{$t->title} (#{$t->id})")->values()->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Sprint $sprint */
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $payload['sprint_id'], __('sprint'));
        $moveTo = is_int($payload['move_to'])
            ? $this->resolver->byId($this->resolver->sprints($user), $payload['move_to'], __('sprint'))
            : (string) $payload['move_to'];

        [$count, $targetName] = $this->completeSprint->handle($user, $sprint, $moveTo);

        return new ToolOutcome(
            CompleteSprint::message($sprint, $count, $targetName),
            $sprint,
            route('sprints.index', $sprint->project_id, false),
        );
    }

    /** The named sprint, or the active sprint of the named project. */
    private function sprint(array $args, User $user): Sprint
    {
        if (!empty($args['sprint'])) {
            return $this->resolver->sprint($user, (string) $args['sprint'], $args['project'] ?? null);
        }
        if (empty($args['project'])) {
            throw new ToolInputException(__('Which sprint, or which project\'s active sprint? Ask the user.'));
        }
        $project = $this->resolver->project($user, (string) $args['project']);

        return $this->resolver->sprints($user)->where('project_id', $project->id)->where('status', 'active')->first()
            ?? throw new ToolInputException(__('Project :project has no active sprint.', ['project' => $project->title]));
    }
}
