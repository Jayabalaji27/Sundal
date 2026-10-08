<?php

namespace App\Services\Ai\Tools;

use App\Actions\ActionException;
use App\Actions\Expenses\DecideExpense;
use App\Models\ProjectExpense;
use App\Models\User;

/**
 * Approve or reject one or many pending expenses. The card lists every
 * expense with its amount; more than 10 needs a typed confirmation.
 */
class DecideExpensesTool extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DecideExpense $decide,
    ) {}

    public function name(): string
    {
        return 'decide_expenses';
    }

    public function description(): string
    {
        return 'Approve or reject pending expenses: by expense ids from list_expense_approvals, or all pending ones for a project and/or person. Rejecting needs a reason. Approving updates the project budget. Shows a confirmation card listing every expense.';
    }

    public function permissions(): array
    {
        return ['expense_approval_approve', 'expense_approval_reject'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'decision' => ['type' => 'enum', 'options' => ['approve', 'reject'], 'required' => true, 'description' => 'Approve or reject.'],
            'expense_ids' => ['type' => 'string', 'description' => 'Comma-separated expense ids, e.g. "4,9".'],
            'project' => ['type' => 'string', 'description' => 'All pending expenses of this project.'],
            'person' => ['type' => 'string', 'description' => 'All pending expenses submitted by this person.'],
            'notes' => ['type' => 'string', 'description' => 'Note to the submitter. Required when rejecting.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $decision = match ($args['decision'] ?? null) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => throw new ToolInputException(__('Say whether to approve or reject.')),
        };

        $permission = $decision === 'approved' ? 'expense_approval_approve' : 'expense_approval_reject';
        if (!$user->hasWorkspacePermission($permission)) {
            throw new ToolInputException(__('This user is not allowed to :action expenses.', ['action' => $decision === 'approved' ? 'approve' : 'reject']));
        }

        $notes = trim((string) ($args['notes'] ?? ''));
        if ($decision === 'rejected' && $notes === '') {
            throw new ToolInputException(__('A reason is required to reject an expense. Ask the user for one.'));
        }

        $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($args['expense_ids'] ?? ''))));
        if (!$ids && empty($args['project']) && empty($args['person'])) {
            throw new ToolInputException(__('Which expenses? Give expense ids, a project or a person.'));
        }

        $query = ListExpenseApprovals::query($this->resolver, $user, $args)->with(['project:id,title', 'submitter:id,name']);
        if ($ids) {
            $query->whereIn('project_expenses.id', $ids);
        }
        $expenses = $query->get()->reject(fn (ProjectExpense $e) => DecideExpense::isOwnExpense($e, $user));

        if ($expenses->isEmpty()) {
            throw new ToolInputException(__('No pending expenses match that this user may review.'));
        }

        $verb = $decision === 'approved' ? __('Approve') : __('Reject');
        $count = $expenses->count();

        return new PreparedAction(
            summary: trans_choice(':verb :count expense|:verb :count expenses', $count, ['verb' => $verb, 'count' => $count]),
            details: array_filter([__('Decision') => $verb, __('Note') => $notes ?: null]),
            payload: ['expense_ids' => $expenses->pluck('id')->values()->all(), 'decision' => $decision, 'notes' => $notes ?: null],
            items: $expenses->map(fn (ProjectExpense $e) => sprintf(
                '%s — %s %s — %s — %s',
                $e->title,
                number_format((float) $e->amount, 2),
                $e->currency,
                $e->project?->title,
                $e->submitter?->name,
            ))->values()->all(),
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? strtoupper($verb) . " {$count}" : null,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $expenses = DecideExpense::reviewable($user)->whereIn('id', $payload['expense_ids'])->where('status', 'pending')->get();
        if ($expenses->count() !== count($payload['expense_ids'])) {
            throw new ToolInputException(__('Some expenses on this card were already decided or are no longer visible to you. Nothing was changed.'));
        }

        foreach ($expenses as $expense) {
            try {
                $this->decide->handle($user, $expense, $payload['decision'], $payload['notes'] ?? null);
            } catch (ActionException $e) {
                throw new ToolInputException(__('Expense ":title": :error Nothing was changed.', ['title' => $expense->title, 'error' => $e->getMessage()]));
            }
        }

        $count = $expenses->count();

        return new ToolOutcome(
            $payload['decision'] === 'approved'
                ? trans_choice('Approved :count expense.|Approved :count expenses.', $count, ['count' => $count])
                : trans_choice('Rejected :count expense.|Rejected :count expenses.', $count, ['count' => $count]),
            null,
            route('expense-approvals.index', [], false),
        );
    }
}
