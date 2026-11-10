<?php

namespace App\Services\Ai\Tools;

use App\Models\ProjectMilestone;
use App\Models\User;

/** Milestones of the projects the user can see, as on each project's Milestones tab. */
class ListMilestones extends AiTool
{
    private const STATUSES = ['pending', 'in_progress', 'completed', 'overdue'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_milestones';
    }

    public function description(): string
    {
        return 'List project milestones with status, due date and progress; can show only overdue or upcoming ones. Filter by project or status.';
    }

    public function permissions(): array
    {
        return ['project_view', 'project_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'status' => ['type' => 'enum', 'options' => self::STATUSES, 'description' => 'Milestone status.'],
            'due' => ['type' => 'enum', 'options' => ['overdue', 'next_30_days'], 'description' => 'overdue: past the due date and not completed; next_30_days: due within 30 days and not completed.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = $this->resolver->milestones($user);
        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, (string) $args['project'])->id);
        }
        if (in_array($args['status'] ?? null, self::STATUSES, true)) {
            $query->where('status', $args['status']);
        }
        $today = now()->toDateString();
        match ($args['due'] ?? null) {
            'overdue' => $query->where('status', '!=', 'completed')->whereDate('due_date', '<', $today),
            'next_30_days' => $query->where('status', '!=', 'completed')->whereBetween('due_date', [$today, now()->addDays(30)->toDateString()]),
            default => null,
        };

        $total = (clone $query)->count();
        $milestones = $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('order')->limit(30)->get();

        return [
            'milestones' => $milestones->map(fn (ProjectMilestone $m) => array_filter([
                'id' => $m->id,
                'title' => $m->title,
                'project' => $m->project?->title,
                'status' => $m->status,
                'due_date' => $m->due_date?->format('Y-m-d'),
                'progress' => (int) $m->progress,
                'overdue' => $m->status !== 'completed' && $m->due_date && $m->due_date->format('Y-m-d') < $today,
                'link' => route('projects.show', ['project' => $m->project_id, 'tab' => 'milestones'], false),
            ], fn ($v) => $v !== null))->all(),
            'shown' => $milestones->count(),
            'total_matching' => $total,
        ];
    }
}
