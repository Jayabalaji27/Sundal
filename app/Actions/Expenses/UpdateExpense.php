<?php

namespace App\Actions\Expenses;

use App\Actions\ActionException;
use App\Events\ExpenseCreated;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Models\Workspace;

/**
 * Changes an expense. Only the keys present in $data change. An expense
 * waiting for more information goes back to pending. Shared by
 * ProjectExpenseController and the AI assistant.
 */
class UpdateExpense
{
    public const FIELDS = ['budget_category_id', 'task_id', 'amount', 'expense_date', 'title', 'description'];

    public function handle(User $actor, ProjectExpense $expense, array $data): ProjectExpense
    {
        if (!self::mayChange($actor, $expense)) {
            throw new ActionException(__('You can only edit your own expenses that have not been approved yet.'));
        }
        self::checkValues($data);

        $changes = array_intersect_key($data, array_flip(self::FIELDS));
        if ($expense->status === 'requires_info') {
            $changes['status'] = 'pending';
        }
        $expense->update($changes);

        $expense->load(['project.clients', 'budgetCategory', 'submitter']);
        if (!config('app.is_demo', true)) {
            event(new ExpenseCreated($expense));
        }

        return $expense;
    }

    /**
     * Members manage their own expenses only, and only until they're approved
     * (approved expenses count against the budget). Owners/managers aren't limited.
     */
    public static function mayChange(User $actor, ProjectExpense $expense): bool
    {
        $workspace = Workspace::find($actor->current_workspace_id);
        if (!$workspace || $workspace->isOwner($actor) || $workspace->getMemberRole($actor) !== 'member') {
            return true;
        }

        return (int) $expense->submitted_by === (int) $actor->id
            && in_array($expense->status, ['pending', 'requires_info'], true);
    }

    /** The screen's rules for the values given: amount ≥ 0, a date not in the future, a title. */
    public static function checkValues(array $data): void
    {
        if (array_key_exists('amount', $data) && (!is_numeric($data['amount']) || (float) $data['amount'] < 0)) {
            throw new ActionException(__('The amount must be a number of 0 or more.'));
        }
        if (array_key_exists('expense_date', $data) && \Carbon\Carbon::parse($data['expense_date'])->startOfDay()->gt(today())) {
            throw new ActionException(__('The expense date cannot be in the future.'));
        }
        if (array_key_exists('title', $data) && (trim((string) $data['title']) === '' || mb_strlen((string) $data['title']) > 255)) {
            throw new ActionException(__('The expense needs a title of at most 255 characters.'));
        }
    }
}
