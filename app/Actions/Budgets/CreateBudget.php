<?php

namespace App\Actions\Budgets;

use App\Actions\ActionException;
use App\Events\BudgetCreated;
use App\Models\BudgetCategory;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a project's budget with its categories. A project has at most one
 * budget. Shared by ProjectBudgetController and the AI assistant.
 *
 * $data: project_id, total_budget, period_type, start_date, end_date?,
 * description?, categories: [{name, allocated_amount, color?, description?, sort_order?}]
 */
class CreateBudget
{
    public const PERIODS = ['project', 'monthly', 'quarterly', 'yearly'];

    public function handle(User $actor, array $data): ProjectBudget
    {
        if (self::existingFor((int) $data['project_id'])) {
            throw new ActionException(__('This project already has a budget. Change that one instead.'));
        }
        if ((float) ($data['total_budget'] ?? 0) <= 0) {
            throw new ActionException(__('The budget must be more than 0.'));
        }
        if (!in_array($data['period_type'] ?? null, self::PERIODS, true)) {
            throw new ActionException(__('The period must be project, monthly, quarterly or yearly.'));
        }
        if (empty($data['categories'])) {
            throw new ActionException(__('A budget needs at least one category.'));
        }

        $project = Project::findOrFail($data['project_id']);

        $budget = DB::transaction(function () use ($actor, $data, $project) {
            $budget = ProjectBudget::create([
                'project_id' => $project->id,
                'workspace_id' => $project->workspace_id,
                'total_budget' => $data['total_budget'],
                'period_type' => $data['period_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach (array_values($data['categories']) as $index => $category) {
                BudgetCategory::create([
                    'project_budget_id' => $budget->id,
                    'name' => $category['name'],
                    'allocated_amount' => $category['allocated_amount'],
                    'color' => $category['color'] ?? '#3B82F6',
                    'description' => $category['description'] ?? '',
                    'sort_order' => $category['sort_order'] ?? ($index + 1),
                ]);
            }

            return $budget;
        });

        if (!config('app.is_demo', true)) {
            event(new BudgetCreated($budget));
        }

        return $budget;
    }

    public static function existingFor(int $projectId): ?ProjectBudget
    {
        return ProjectBudget::where('project_id', $projectId)->first();
    }
}
