<?php

namespace App\Services\Ai\Tools;

use App\Actions\Sprints\CompleteSprint;
use App\Models\Sprint;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Plans a new sprint in a project, as the project's Sprints page does. */
class CreateSprintTool extends AiTool implements HasForm
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'create_sprint';
    }

    public function description(): string
    {
        return 'Plan a new sprint in a project (it starts as planned; start it with start_sprint). Without a name it is called "Sprint N". Shows a confirmation card; nothing is created until the user confirms. Fill it with add_tasks_to_sprint.';
    }

    public function permissions(): array
    {
        return ['sprint_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'name' => ['type' => 'string', 'description' => 'Sprint name. Leave out for the next "Sprint N".'],
            'goal' => ['type' => 'string', 'description' => 'What the sprint should achieve.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, not before the start date.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Plan a sprint');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('name', __('Name'), 'text'),
            new FormField('start_date', __('Start'), 'date'),
            new FormField('end_date', __('End'), 'date'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $name = trim((string) ($args['name'] ?? '')) ?: CompleteSprint::nextName($project->id);
        if (mb_strlen($name) > 255) {
            throw new ToolInputException(__('The sprint needs a name of at most 255 characters.'));
        }
        if (Sprint::where('project_id', $project->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new ToolInputException(__('Project :project already has a sprint called ":name". Ask the user whether they meant that one.', ['project' => $project->title, 'name' => $name]));
        }
        $start = $this->resolver->date($args['start_date'] ?? null, __('Start date'));
        $end = $this->resolver->date($args['end_date'] ?? null, __('End date'));
        if ($start && $end && $end < $start) {
            throw new ToolInputException(__('The sprint must end on or after its start date.'));
        }
        $goal = isset($args['goal']) && trim((string) $args['goal']) !== '' ? mb_substr(trim((string) $args['goal']), 0, 1000) : null;

        return new PreparedAction(
            summary: __('Plan :name in :project', ['name' => $name, 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Name') => $name,
                __('Goal') => $goal,
                __('Start') => $start,
                __('End') => $end,
            ]),
            payload: ['project_id' => $project->id, 'name' => $name, 'goal' => $goal, 'start_date' => $start, 'end_date' => $end],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));

        $sprint = Sprint::create([
            'name' => $payload['name'],
            'goal' => $payload['goal'],
            'start_date' => $payload['start_date'],
            'end_date' => $payload['end_date'],
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'status' => 'planning',
            'created_by' => $user->id,
        ]);

        return new ToolOutcome(
            __('Planned :name in :project.', ['name' => $sprint->name, 'project' => $project->title]),
            $sprint,
            route('sprints.index', $project->id, false),
            ['sprint_id' => $sprint->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Sprint $sprint */
        $sprint = $this->resolver->byId($this->resolver->sprints($user), (int) $undo['sprint_id'], __('sprint'));
        if ($sprint->status !== 'planning' || $sprint->tasks()->exists()) {
            throw new ToolInputException(__(':name was started or has tasks now, so it was not undone.', ['name' => $sprint->name]));
        }
        $sprint->delete();

        return __(':name removed.', ['name' => $sprint->name]);
    }
}
