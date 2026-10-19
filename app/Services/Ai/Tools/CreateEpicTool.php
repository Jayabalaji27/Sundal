<?php

namespace App\Services\Ai\Tools;

use App\Models\Epic;
use App\Models\User;

/** Creates an epic in a project, as the project's Epics tab does. */
class CreateEpicTool extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'create_epic';
    }

    public function description(): string
    {
        return 'Create an epic in a project: a big piece of work (a feature, a module) to group tasks and bugs under. Shows the user a confirmation card; nothing is created until they confirm. To put items into it afterwards, use set_epic.';
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
            'name' => ['type' => 'string', 'required' => true, 'description' => 'The epic name only, e.g. "Checkout and payments". At most 255 characters.'],
            'description' => ['type' => 'string', 'description' => 'What the epic delivers.'],
            'owner' => ['type' => 'string', 'description' => '"me" or the name or email of someone on the project.'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
            'target_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, not before the start date.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new ToolInputException(__('The epic needs a name of at most 255 characters.'));
        }
        if ($project->epics()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            throw new ToolInputException(__('Project :project already has an epic called ":name". Ask the user whether they meant that one.', ['project' => $project->title, 'name' => $name]));
        }

        $owner = null;
        if (!empty($args['owner'])) {
            $owner = $this->resolver->member($user, (string) $args['owner']);
            $team = $project->members()->pluck('user_id')->push($project->created_by)->all();
            if (!in_array($owner->id, $team, true)) {
                throw new ToolInputException(__(':name is not on project :project, so cannot own its epic.', ['name' => $owner->name, 'project' => $project->title]));
            }
        }

        $start = $this->resolver->date($args['start_date'] ?? null, __('Start date'));
        $target = $this->resolver->date($args['target_date'] ?? null, __('Target date'));
        if ($start && $target && $target < $start) {
            throw new ToolInputException(__('The target date cannot be before the start date.'));
        }
        $description = isset($args['description']) && trim((string) $args['description']) !== '' ? mb_substr(trim((string) $args['description']), 0, 5000) : null;

        return new PreparedAction(
            summary: __('Create epic ":name" in :project', ['name' => $name, 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Name') => $name,
                __('Owner') => $owner?->name,
                __('Start') => $start,
                __('Target') => $target,
            ]),
            payload: [
                'project_id' => $project->id,
                'name' => $name,
                'description' => $description,
                'owner_id' => $owner?->id,
                'start_date' => $start,
                'end_date' => $target,
            ],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));

        $epic = Epic::create([
            ...$payload,
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'color' => Epic::COLORS[$project->epics()->count() % count(Epic::COLORS)],
            'status' => 'open',
        ]);

        return new ToolOutcome(
            __('Created epic ":name" in :project.', ['name' => $epic->name, 'project' => $project->title]),
            $epic,
            route('epics.index', $project->id, false),
        );
    }
}
