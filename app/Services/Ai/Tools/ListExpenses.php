<?php

namespace App\Services\Ai\Tools;

use App\Models\ProjectExpense;
use App\Models\User;

/** Expenses the user can see, by the same rule as the Expenses screen. */
class ListExpenses extends AiTool
{
    private const STATUSES = ['pending', 'requires_info', 'approved', 'rejected'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_expenses';
    }

    public function description(): string
    {
        return 'List project expenses the user can see, with id, title, amount, date, status, category and who submitted it. Filter by project, status or search text.';
    }

    public function permissions(): array
    {
        return ['expense_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'status' => ['type' => 'enum', 'options' => self::STATUSES, 'description' => 'Expense status.'],
            'search' => ['type' => 'string', 'description' => 'Words in the title.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = ProjectExpense::visibleTo($user)->with(['project:id,title', 'budgetCategory:id,name', 'submitter:id,name']);

        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, (string) $args['project'])->id);
        }
        if (!empty($args['status']) && in_array($args['status'], self::STATUSES, true)) {
            $query->where('project_expenses.status', $args['status']);
        }
        if (!empty($args['search'])) {
            $query->where('title', 'like', '%' . addcslashes((string) $args['search'], '%_\\') . '%');
        }

        $expenses = $query->latest('expense_date')->latest('id')->limit(30)->get();

        return [
            'expenses' => $expenses->map(fn (ProjectExpense $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'date' => $e->expense_date?->format('Y-m-d'),
                'status' => $e->status,
                'project' => $e->project?->title,
                'category' => $e->budgetCategory?->name,
                'submitted_by' => $e->submitter?->name,
                'link' => route('expenses.show', $e->id, false),
            ])->all(),
            'shown' => $expenses->count(),
            'total' => round((float) $expenses->sum('amount'), 2),
        ];
    }
}
