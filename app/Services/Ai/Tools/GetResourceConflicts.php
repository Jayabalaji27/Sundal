<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\ResourceConflicts;

/** The Resource Conflicts page: people with too much due in the next 14 days. */
class GetResourceConflicts extends AiTool
{
    public function __construct(private readonly ResourceConflicts $conflicts) {}

    public function name(): string
    {
        return 'get_resource_conflicts';
    }

    public function description(): string
    {
        return 'Resource Conflicts: people with three or more open tasks due in the next 14 days, how severe the overload is, their tasks and a recommendation. Use it for "who is overloaded?" or before assigning more work.';
    }

    public function permissions(): array
    {
        return ['agent_advanced_insights'];
    }

    public function run(array $args, User $user): array
    {
        $conflicts = collect($this->conflicts->forUser($user));

        return [
            'people' => $conflicts->map(fn (array $c) => [
                'name' => $c['user']['name'],
                'open_tasks_due_in_14_days' => (int) $c['task_count'],
                'high_priority' => (int) $c['high_priority_count'],
                'earliest_deadline' => $c['earliest_deadline'],
                'severity' => $c['severity'],
                'recommendation' => $c['recommendation'],
                'tasks' => collect($c['tasks'])->take(8)->map(fn ($t) => [
                    'title' => $t->title,
                    'project' => $t->project,
                    'priority' => $t->priority,
                    'due' => $t->end_date ? substr((string) $t->end_date, 0, 10) : null,
                ])->all(),
            ])->all(),
            'overloaded' => $conflicts->count(),
            'link' => route('resource-conflicts.index', [], false),
        ];
    }
}
