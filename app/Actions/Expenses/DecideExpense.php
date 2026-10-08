<?php

namespace App\Actions\Expenses;

use App\Actions\ActionException;
use App\Events\ExpenseApprovalRequested;
use App\Models\ExpenseApproval;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BudgetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects an expense. Approval updates the project budget.
 * Shared by ExpenseApprovalController and the AI assistant.
 */
class DecideExpense
{
    public function __construct(private readonly BudgetService $budgetService) {}

    /**
     * Expenses the actor may review: the whole workspace for the owner,
     * projects they created or are a member of for a manager.
     */
    public static function reviewable(User $actor): Builder
    {
        $workspace = Workspace::find($actor->current_workspace_id);

        $query = ProjectExpense::whereHas('project', fn ($q) => $q->where('workspace_id', $actor->current_workspace_id));

        if ($workspace && !$workspace->isOwner($actor) && $workspace->getMemberRole($actor) === 'manager') {
            $query->whereHas('project', fn ($q) => $q->where(function ($projectQuery) use ($actor) {
                $projectQuery->whereHas('members', fn ($m) => $m->where('user_id', $actor->id))
                    ->orWhere('created_by', $actor->id);
            }));
        }

        return $query;
    }

    /**
     * Nobody reviews their own expense, except the workspace owner: there is no
     * one above them to send it to.
     */
    public static function isOwnExpense(ProjectExpense $expense, User $actor): bool
    {
        return (int) $expense->submitted_by === (int) $actor->id
            && !Workspace::find($actor->current_workspace_id)?->isOwner($actor);
    }

    public function handle(User $actor, ProjectExpense $expense, string $decision, ?string $notes = null): ProjectExpense
    {
        if (self::isOwnExpense($expense, $actor)) {
            throw new ActionException(__('You cannot approve or reject your own expense. The workspace owner reviews it.'));
        }
        if ($decision === 'rejected' && trim((string) $notes) === '') {
            throw new ActionException(__('A reason is required to reject an expense.'));
        }

        DB::transaction(function () use ($actor, $expense, $decision, $notes) {
            ExpenseApproval::updateOrCreate(
                ['project_expense_id' => $expense->id, 'approver_id' => $actor->id],
                ['status' => $decision, 'notes' => $notes, 'approved_at' => now(), 'approval_level' => 1]
            );

            $expense->update(['status' => $decision]);

            if ($decision === 'approved') {
                $this->budgetService->updateBudgetAfterApproval($expense);
                if (!config('app.is_demo', true)) {
                    event(new ExpenseApprovalRequested($expense));
                }
            }
        });

        return $expense;
    }
}
