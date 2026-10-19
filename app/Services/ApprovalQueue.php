<?php

namespace App\Services;

use App\Models\ProjectExpense;
use App\Models\TimesheetApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The reviewer's queue for timesheets and expenses. The dashboard counts, the
 * Approvals inbox and the approve/reject actions all read from here, so the
 * number on the dashboard is always the number of rows the reviewer can act on.
 *
 * Owners see the whole workspace. Managers see the projects they belong to or
 * created. Nobody reviews their own item except the owner, who has no one above
 * them: those items stay visible in the list but are not counted as waiting.
 */
class ApprovalQueue
{
    /** Expense statuses still waiting on a reviewer's decision. */
    public const WAITING_EXPENSE_STATUSES = ['pending', 'requires_info'];

    /** Timesheet approvals the user can see in the inbox. */
    public function timesheets(User $user): Builder
    {
        $workspace = $user->currentWorkspace;
        $query = TimesheetApproval::query()
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $workspace?->id));

        if (!$workspace) {
            return $query->whereRaw('1 = 0');
        }
        if ($workspace->isOwner($user)) {
            return $query;
        }
        if ($workspace->getMemberRole($user) !== 'manager') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('timesheet.entries.project', fn ($q) => $this->managedBy($q, $user));
    }

    /** Project expenses the user can see in the inbox. */
    public function expenses(User $user): Builder
    {
        $workspace = $user->currentWorkspace;
        $query = ProjectExpense::query()
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspace?->id));

        if (!$workspace) {
            return $query->whereRaw('1 = 0');
        }
        if ($workspace->isOwner($user)) {
            return $query;
        }
        if ($workspace->getMemberRole($user) !== 'manager') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('project', fn ($q) => $this->managedBy($q, $user));
    }

    /** The part of the timesheet queue the user may decide on. */
    public function reviewableTimesheets(User $user): Builder
    {
        $query = $this->timesheets($user);

        return $this->isOwner($user)
            ? $query
            : $query->whereHas('timesheet', fn ($q) => $q->where('user_id', '!=', $user->id));
    }

    /** The part of the expense queue the user may decide on. */
    public function reviewableExpenses(User $user): Builder
    {
        $query = $this->expenses($user);

        return $this->isOwner($user)
            ? $query
            : $query->where('project_expenses.submitted_by', '!=', $user->id);
    }

    public function pendingTimesheetCount(User $user): int
    {
        return $this->reviewableTimesheets($user)->where('timesheet_approvals.status', 'pending')->count();
    }

    public function waitingExpenseCount(User $user): int
    {
        return $this->reviewableExpenses($user)->whereIn('project_expenses.status', self::WAITING_EXPENSE_STATUSES)->count();
    }

    /** Badge counts for the inbox tabs, only for the tabs the user can open. */
    public function counts(User $user): array
    {
        $counts = [];
        if ($user->can('timesheet_approve')) {
            $counts['timesheets'] = $this->pendingTimesheetCount($user);
        }
        if ($user->can('expense_approval_view_any')) {
            $counts['expenses'] = $this->waitingExpenseCount($user);
        }

        return $counts;
    }

    public function inTimesheetQueue(User $user, TimesheetApproval $approval): bool
    {
        return $this->timesheets($user)->whereKey($approval->getKey())->exists();
    }

    public function inExpenseQueue(User $user, ProjectExpense $expense): bool
    {
        return $this->expenses($user)->whereKey($expense->getKey())->exists();
    }

    private function isOwner(User $user): bool
    {
        return (bool) $user->currentWorkspace?->isOwner($user);
    }

    private function managedBy(Builder $projects, User $user): void
    {
        $projects->where(fn ($q) => $q
            ->whereHas('members', fn ($m) => $m->where('user_id', $user->id))
            ->orWhere('created_by', $user->id));
    }
}
