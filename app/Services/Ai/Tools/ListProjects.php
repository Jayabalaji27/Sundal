<?php

namespace App\Services\Ai\Tools;

use App\Models\Project;
use App\Models\User;

class ListProjects extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'List projects the user can see, with status, progress, deadline and open/overdue task counts. Use it for project status questions.';
    }

    public function permissions(): array
    {
        return ['project_view_any'];
    }

    public function parameters(): array
    {
        return [
            'search' => ['type' => 'string', 'description' => 'Part of the project title to filter by.'],
            'status' => ['type' => 'string', 'description' => 'Project status to filter by, e.g. active, on_hold, completed.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $today = now()->toDateString();
        $openTasks = fn ($q) => $q->whereHas('taskStage', fn ($s) => $s->where('is_completed', false));

        $projects = $this->resolver->projects($user)
            ->when($args['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%' . addcslashes($search, '%_\\') . '%'))
            ->when($args['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->withCount([
                'tasks as open_tasks' => $openTasks,
                'tasks as overdue_tasks' => fn ($q) => $openTasks($q)->whereDate('end_date', '<', $today),
            ])
            ->orderBy('title')
            ->limit(25)
            ->get();

        return [
            'projects' => $projects->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'priority' => $p->priority,
                'progress_percent' => (int) $p->progress,
                'deadline' => $p->deadline?->format('Y-m-d'),
                'open_tasks' => $p->open_tasks,
                'overdue_tasks' => $p->overdue_tasks,
                'link' => route('projects.show', $p->id, false),
            ])->all(),
            'shown' => $projects->count(),
        ];
    }
}
