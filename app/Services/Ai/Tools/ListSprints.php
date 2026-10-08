<?php

namespace App\Services\Ai\Tools;

use App\Models\Sprint;
use App\Models\User;

class ListSprints extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_sprints';
    }

    public function description(): string
    {
        return 'List sprints of the projects the user can see, with status, dates and task counts.';
    }

    public function permissions(): array
    {
        return ['sprint_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'status' => ['type' => 'enum', 'options' => ['planning', 'active', 'completed'], 'description' => 'Sprint status.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = Sprint::query()
            ->where('workspace_id', $user->current_workspace_id)
            ->whereIn('project_id', $this->resolver->projects($user)->select('id'))
            ->with('project:id,title')
            ->withCount('tasks');

        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, (string) $args['project'])->id);
        }
        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }

        $sprints = $query->latest('id')->limit(30)->get();

        return [
            'sprints' => $sprints->map(fn (Sprint $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'project' => $s->project?->title,
                'status' => $s->status,
                'goal' => $s->goal,
                'start_date' => $s->start_date?->format('Y-m-d'),
                'end_date' => $s->end_date?->format('Y-m-d'),
                'tasks' => $s->tasks_count,
                'link' => route('sprints.show', $s->id, false),
            ])->all(),
        ];
    }
}
