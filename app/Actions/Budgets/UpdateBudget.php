<?php

namespace App\Actions\Budgets;

use App\Actions\ActionException;
use App\Models\BudgetCategory;
use App\Models\ProjectBudget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes a budget. Only the keys present in $data change: project_id,
 * total_budget, period_type, description, status, and categories
 * ([{id?, name, allocated_amount, color?, description?}], replacing the
 * list: ones left out are deleted). Shared by ProjectBudgetController and
 * the AI assistant.
 */
class UpdateBudget
{
    public const FIELDS = ['project_id', 'total_budget', 'period_type', 'description', 'status'];
    public const STATUSES = ['active', 'completed', 'cancelled'];

    public function handle(User $actor, ProjectBudget $budget, array $data): ProjectBudget
    {
        if (array_key_exists('total_budget', $data) && (float) $data['total_budget'] <= 0) {
            throw new ActionException(__('The budget must be more than 0.'));
        }
        if (array_key_exists('period_type', $data) && !in_array($data['period_type'], CreateBudget::PERIODS, true)) {
            throw new ActionException(__('The period must be project, monthly, quarterly or yearly.'));
        }
        if (array_key_exists('status', $data) && !in_array($data['status'], self::STATUSES, true)) {
            throw new ActionException(__('The status must be active, completed or cancelled.'));
        }
        if (array_key_exists('categories', $data) && empty($data['categories'])) {
            throw new ActionException(__('A budget needs at least one category.'));
        }

        DB::transaction(function () use ($budget, $data) {
            $budget->update(array_intersect_key($data, array_flip(self::FIELDS)));

            if (!array_key_exists('categories', $data)) {
                return;
            }

            $kept = [];
            foreach (array_values($data['categories']) as $index => $category) {
                $existing = !empty($category['id']) ? $budget->categories()->whereKey($category['id'])->first() : null;
                if ($existing) {
                    $existing->update([...$category, 'sort_order' => $index + 1]);
                    $kept[] = $existing->id;
                } else {
                    $kept[] = $budget->categories()->create([
                        'name' => $category['name'],
                        'allocated_amount' => $category['allocated_amount'],
                        'color' => $category['color'] ?? '#3B82F6',
                        'description' => $category['description'] ?? '',
                        'sort_order' => $index + 1,
                    ])->id;
                }
            }

            $budget->categories()->whereNotIn('id', $kept)->delete();
        });

        return $budget;
    }
}
