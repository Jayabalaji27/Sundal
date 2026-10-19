<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;

class ListBugs extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_bugs';
    }

    public function description(): string
    {
        return 'List bugs the user can see. Bugs are tasks of type bug and move through the same stages. Filter by project, assignee ("me", a name, or "unassigned"), stage, severity or title.';
    }

    public function permissions(): array
    {
        return ['bug_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'assignee' => ['type' => 'string', 'description' => '"me", a person\'s name or email, or "unassigned".'],
            'status' => ['type' => 'string', 'description' => 'Stage name, e.g. To Do, In Progress, Done. "Resolved" or "Closed" mean the done stage.'],
            'severity' => ['type' => 'enum', 'options' => Task::SEVERITIES, 'description' => 'Bug severity.'],
            'search' => ['type' => 'string', 'description' => 'Part of the bug title.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = $this->resolver->bugs($user)->with(['project:id,title', 'taskStage:id,name', 'assignedTo:id,name', 'creator:id,name', 'epic:id,name']);

        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, $args['project'])->id);
        }
        if (!empty($args['assignee'])) {
            strtolower(trim($args['assignee'])) === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $this->resolver->member($user, $args['assignee'])->id);
        }
        if (!empty($args['status'])) {
            $query->where('task_stage_id', $this->resolver->bugStage($user, $args['status'])->id);
        }
        if (!empty($args['severity'])) {
            $query->where('severity', $args['severity']);
        }
        if (!empty($args['search'])) {
            $query->where('title', 'like', '%' . addcslashes($args['search'], '%_\\') . '%');
        }

        $total = (clone $query)->count();
        $bugs = $query->latest('id')->limit(25)->get();

        return [
            'bugs' => $bugs->map(fn (Task $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'project' => $b->project?->title,
                'status' => $b->taskStage?->name,
                'severity' => $b->severity,
                'epic' => $b->epic?->name,
                'priority' => $b->priority,
                'assignee' => $b->assignedTo?->name,
                'reported_by' => $b->creator?->name,
                'due_date' => $b->end_date?->format('Y-m-d'),
                'link' => route('tasks.show', $b->id, false),
            ])->all(),
            'shown' => $bugs->count(),
            'total_matching' => $total,
        ];
    }
}
