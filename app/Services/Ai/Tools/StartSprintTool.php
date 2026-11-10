<?php

namespace App\Services\Ai\Tools;

use App\Actions\Sprints\StartSprint;
use App\Models\Sprint;
use App\Models\User;
use Carbon\Carbon;

/** Starts a planned sprint, as the Start button on the Sprints page does. */
class StartSprintTool extends AiTool
{
    use RevertsChanges;

    private const FIELDS = ['status', 'start_date', 'end_date', 'goal'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly StartSprint $startSprint,
    ) {}

    public function name(): string
    {
        return 'start_sprint';
    }

    public function description(): string
    {
        return 'Start a planned sprint. A project has one active sprint at a time. Without dates it starts today and runs two weeks (or keeps its planned dates). Shows a confirmation card first.';
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
            'sprint' => ['type' => 'string', 'required' => true, 'description' => 'Sprint name or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id; sprint names repeat across projects.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'goal' => ['type' => 'string', 'description' => 'The sprint goal, when the user gave one.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $sprint = $this->resolver->sprint($user, (string) ($args['sprint'] ?? ''), $args['project'] ?? null);

        if ($sprint->status !== 'planning') {
            throw new ToolInputException(__('Only a planned sprint can be started; :name is :status.', ['name' => $sprint->name, 'status' => $sprint->status]));
        }
        if ($active = $this->startSprint->activeSprint($sprint)) {
            throw new ToolInputException(__('Complete ":name" before starting another sprint.', ['name' => $active->name]));
        }

        $data = array_filter([
            'start_date' => $this->resolver->date($args['start_date'] ?? null, __('Start date')),
            'end_date' => $this->resolver->date($args['end_date'] ?? null, __('End date')),
            'goal' => isset($args['goal']) && trim((string) $args['goal']) !== '' ? mb_substr(trim((string) $args['goal']), 0, 1000) : null,
        ], fn ($v) => $v !== null);

        $start = Carbon::parse($data['start_date'] ?? $sprint->start_date ?? now());
        $end = isset($data['end_date']) ? Carbon::parse($data['end_date']) : ($sprint->end_date ?? $start->copy()->addWeeks(2));
        if ($end->lt($start->copy()->startOfDay())) {
            throw new ToolInputException(__('The sprint must end on or after its start date.'));
        }

        return new PreparedAction(
            summary: __('Start :name in :project', ['name' => $sprint->name, 'project' => $sprint->project?->title]),
            details: array_filter([
                __('Sprint') => $sprint->name,
                __('Project') => $sprint->project?->title,
                __('Runs') => $start->toDateString() . ' → ' . $end->toDateString(),
                __('Goal') => $data['goal'] ?? $sprint->goal,
                __('Tasks') => (string) $sprint->tasks()->count(),
            ]),
            payload: ['sprint_id' => $sprint->id, 'data' => $data],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Sprint $sprint */
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $payload['sprint_id'], __('sprint'));
        $before = $this->snapshot($sprint, self::FIELDS);

        $this->startSprint->handle($sprint, $payload['data']);

        return new ToolOutcome(
            __(':name started.', ['name' => $sprint->name]),
            $sprint,
            route('sprints.index', $sprint->project_id, false),
            $this->changeUndo($sprint, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Sprint $sprint */
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $undo['id'], __('sprint'));
        $this->ensureUnchanged($sprint, $undo, $sprint->name);

        $sprint->update($undo['before']);

        return __(':name is planned again.', ['name' => $sprint->name]);
    }
}
