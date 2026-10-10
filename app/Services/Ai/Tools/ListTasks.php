<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;

class ListTasks extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'List tasks (including stories) the user can see. Filter by project, assignee ("me", a name, or "unassigned"), stage, overdue, or due within N days.';
    }

    public function permissions(): array
    {
        return ['task_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'assignee' => ['type' => 'string', 'description' => '"me", a person\'s name or email, or "unassigned".'],
            'stage' => ['type' => 'string', 'description' => 'Task stage name, e.g. To Do, In Progress, Done.'],
            'search' => ['type' => 'string', 'description' => 'Part of the task title.'],
            'overdue' => ['type' => 'boolean', 'description' => 'Only open tasks past their due date.'],
            'due_within_days' => ['type' => 'number', 'description' => 'Only open tasks due between today and this many days from now.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = $this->resolver->tasks($user)->with(['project:id,title', 'taskStage:id,name,is_completed', 'assignedTo:id,name']);

        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, $args['project'])->id);
        }
        if (!empty($args['assignee'])) {
            strtolower(trim($args['assignee'])) === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $this->resolver->member($user, $args['assignee'])->id);
        }
        if (!empty($args['stage'])) {
            $query->where('task_stage_id', $this->resolver->taskStage($user, $args['stage'])->id);
        }
        if (!empty($args['search'])) {
            $query->where('title', 'like', '%' . addcslashes($args['search'], '%_\\') . '%');
        }

        $openOnly = fn ($q) => $q->whereHas('taskStage', fn ($s) => $s->where('is_completed', false));
        if (!empty($args['overdue'])) {
            $openOnly($query)->whereDate('end_date', '<', now()->toDateString());
        }
        if (!empty($args['due_within_days'])) {
            $days = max(0, min(365, (int) $args['due_within_days']));
            $openOnly($query)->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        }

        $total = (clone $query)->count();
        $tasks = $query->orderByRaw('end_date IS NULL, end_date')->limit(25)->get();

        return [
            'tasks' => $tasks->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'project' => $t->project?->title,
                'stage' => $t->taskStage?->name,
                'assignee' => $t->assignedTo?->name,
                'priority' => $t->priority,
                'due_date' => $t->end_date?->format('Y-m-d'),
                'link' => route('tasks.show', $t->id, false),
            ])->all(),
            'shown' => $tasks->count(),
            'total_matching' => $total,
        ];
    }
}
