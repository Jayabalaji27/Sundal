<?php

namespace App\Actions\Expenses;

use App\Actions\ActionException;
use App\Models\ProjectExpense;
use App\Models\User;

/** Deletes an expense. Shared by ProjectExpenseController and the AI assistant. */
class DeleteExpense
{
    public function handle(User $actor, ProjectExpense $expense): void
    {
        if (!UpdateExpense::mayChange($actor, $expense)) {
            throw new ActionException(__('You can only delete your own expenses that have not been approved yet.'));
        }

        $expense->delete();
    }
}
