<?php

namespace App\Actions\Expenses;

use App\Actions\ActionException;
use App\Events\ExpenseCreated;
use App\Models\ProjectExpense;
use App\Models\User;

/**
 * Submits a project expense for approval (status pending). Shared by
 * ProjectExpenseController and the AI assistant.
 *
 * $data: project_id, budget_category_id?, task_id?, amount, expense_date
 * (not in the future), title, description?
 */
class CreateExpense
{
    public function handle(User $actor, array $data): ProjectExpense
    {
        UpdateExpense::checkValues($data);

        $expense = ProjectExpense::create([
            ...array_intersect_key($data, array_flip(['project_id', 'budget_category_id', 'task_id', 'amount', 'expense_date', 'title', 'description'])),
            'submitted_by' => $actor->id,
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        $expense->load(['project.clients', 'budgetCategory', 'submitter']);
        if (!config('app.is_demo', true)) {
            event(new ExpenseCreated($expense));
        }

        return $expense;
    }
}
