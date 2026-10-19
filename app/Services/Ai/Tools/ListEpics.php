<?php

namespace App\Services\Ai\Tools;

use App\Models\Epic;
use App\Models\Task;
use App\Models\User;

/** Epics (big pieces of work grouping tasks and bugs) with their progress. */
class ListEpics extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_epics';
    }

    public function description(): string
    {
        return 'List the epics of the projects the user can see: big pieces of work (a feature, a module) that group tasks and bugs across sprints, with how many of their items are done. Filter by project, status or name; ask for one epic to get its items.';
    }

    public function permissions(): array
    {
        return ['sprint_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'status' => ['type' => 'enum', 'options' => Epic::STATUSES, 'description' => 'open or done. Leave out for both.'],
            'search' => ['type' => 'string', 'description' => 'Part of the epic name. With one match, its items are listed too.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = $this->resolver->epics($user)->with(['owner:id,name', 'tasks.taskStage:id,name,is_completed']);
        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, $args['project'])->id);
        }
        if (!empty($args['status']) && in_array($args['status'], Epic::STATUSES, true)) {
            $query->where('status', $args['status']);
        }
        if (!empty($args['search'])) {
            $query->where('name', 'like', '%' . addcslashes($args['search'], '%_\\') . '%');
        }

        $epics = $query->orderByRaw("FIELD(status, 'open', 'done')")->orderBy('name')->limit(25)->get();
        $withItems = $epics->count() === 1;

        return [
            'epics' => $epics->map(function (Epic $epic) use ($withItems) {
                $done = $epic->tasks->filter(fn (Task $t) => $t->isCompleted());

                return array_filter([
                    'id' => $epic->id,
                    'name' => $epic->name,
                    'project' => $epic->project?->title,
                    'status' => $epic->status,
                    'owner' => $epic->owner?->name,
                    'start_date' => $epic->start_date?->format('Y-m-d'),
                    'target_date' => $epic->end_date?->format('Y-m-d'),
                    'items' => $epic->tasks->count(),
                    'items_done' => $done->count(),
                    'bugs' => $epic->tasks->where('type', 'bug')->count(),
                    'points' => (int) $epic->tasks->sum('story_points'),
                    'items_list' => $withItems ? $epic->tasks->take(30)->map(fn (Task $t) => [
                        'id' => $t->id, 'title' => $t->title, 'type' => $t->type, 'stage' => $t->taskStage?->name,
                    ])->values()->all() : null,
                    'link' => route('epics.index', $epic->project_id, false),
                ], fn ($v) => $v !== null);
            })->all(),
            'shown' => $epics->count(),
        ];
    }
}
