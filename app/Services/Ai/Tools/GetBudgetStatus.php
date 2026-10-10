<?php

namespace App\Services\Ai\Tools;

use App\Models\Project;
use App\Models\User;
use App\Services\BudgetService;

class GetBudgetStatus extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly BudgetService $budgets,
    ) {}

    public function name(): string
    {
        return 'get_budget_status';
    }

    public function description(): string
    {
        return 'Budget, spend, remaining amount and utilisation per project, with categories. Use it for "who is over budget" questions; leave project empty to check all projects.';
    }

    public function permissions(): array
    {
        return ['budget_view_any', 'budget_view'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id; empty for all projects with a budget.'],
            'only_at_risk' => ['type' => 'boolean', 'description' => 'Only projects at 75% or more of their budget.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $projects = !empty($args['project'])
            ? collect([$this->resolver->project($user, (string) $args['project'])])
            : $this->resolver->projects($user)->whereHas('budget')->orderBy('title')->limit(50)->get();

        $rows = $projects->map(function (Project $project) {
            $summary = $this->budgets->getProjectBudgetSummary($project->id);
            if (!$summary) {
                return ['project' => $project->title, 'budget' => null];
            }

            return [
                'project' => $project->title,
                'total_budget' => (float) $summary['total_budget'],
                'spent' => (float) $summary['total_spent'],
                'remaining' => (float) $summary['remaining_budget'],
                'utilization_percent' => round((float) $summary['utilization_percentage'], 1),
                'currency' => $summary['currency'],
                'status' => $summary['status'],
                'categories' => collect($summary['categories'])->map(fn ($c) => [
                    'name' => $c['name'],
                    'allocated' => (float) $c['allocated_amount'],
                    'spent' => (float) $c['spent_amount'],
                    'over_budget' => (bool) $c['is_over_budget'],
                ])->all(),
            ];
        });

        if (!empty($args['only_at_risk'])) {
            $rows = $rows->filter(fn ($row) => in_array($row['status'] ?? null, ['warning', 'critical', 'over_budget'], true));
        }

        return ['budgets' => $rows->values()->all()];
    }
}
