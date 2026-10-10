<?php

namespace App\Services\Ai\Tools;

use App\Models\Bug;
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
        return 'List bugs the user can see. Filter by project, assignee ("me", a name, or "unassigned"), status, severity or title.';
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
            'status' => ['type' => 'string', 'description' => 'Bug status name, e.g. New, In Progress, Resolved.'],
            'severity' => ['type' => 'enum', 'options' => ['minor', 'major', 'critical', 'blocker'], 'description' => 'Bug severity.'],
            'search' => ['type' => 'string', 'description' => 'Part of the bug title.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = $this->resolver->bugs($user)->with(['project:id,title', 'bugStatus:id,name', 'assignedTo:id,name', 'reportedBy:id,name']);

        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, $args['project'])->id);
        }
        if (!empty($args['assignee'])) {
            strtolower(trim($args['assignee'])) === 'unassigned'
                ? $query->whereNull('assigned_to')
                : $query->where('assigned_to', $this->resolver->member($user, $args['assignee'])->id);
        }
        if (!empty($args['status'])) {
            $query->where('bug_status_id', $this->resolver->bugStatus($user, $args['status'])->id);
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
            'bugs' => $bugs->map(fn (Bug $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'project' => $b->project?->title,
                'status' => $b->bugStatus?->name,
                'severity' => $b->severity,
                'priority' => $b->priority,
                'assignee' => $b->assignedTo?->name,
                'reported_by' => $b->reportedBy?->name,
                'due_date' => $b->end_date?->format('Y-m-d'),
                'link' => route('bugs.show', $b->id, false),
            ])->all(),
            'shown' => $bugs->count(),
            'total_matching' => $total,
        ];
    }
}
