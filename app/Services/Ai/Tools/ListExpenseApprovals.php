<?php

namespace App\Services\Ai\Tools;

use App\Actions\Expenses\DecideExpense;
use App\Models\ProjectExpense;
use App\Models\User;

class ListExpenseApprovals extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_expense_approvals';
    }

    public function description(): string
    {
        return 'List expenses waiting for approval (or already decided) that this user may review, with amount, project and who submitted them. Use the ids with decide_expenses.';
    }

    public function permissions(): array
    {
        return ['expense_approval_view_any'];
    }

    public function parameters(): array
    {
        return [
            'status' => ['type' => 'enum', 'options' => ['pending', 'approved', 'rejected', 'requires_info'], 'description' => 'Defaults to pending.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'person' => ['type' => 'string', 'description' => 'Who submitted it: a name or email.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $expenses = self::query($this->resolver, $user, $args)
            ->with(['project:id,title', 'submitter:id,name'])
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'expenses' => $expenses->map(fn (ProjectExpense $e) => [
                'expense_id' => $e->id,
                'title' => $e->title,
                'amount' => (float) $e->amount,
                'currency' => $e->currency,
                'date' => $e->expense_date?->format('Y-m-d'),
                'project' => $e->project?->title,
                'submitted_by' => $e->submitter?->name,
                'status' => $e->status,
            ])->all(),
            'shown' => $expenses->count(),
        ];
    }

    public static function query(RecordResolver $resolver, User $user, array $args)
    {
        $query = DecideExpense::reviewable($user)->where('status', $args['status'] ?? 'pending');

        if (!empty($args['project'])) {
            $query->where('project_id', $resolver->project($user, (string) $args['project'])->id);
        }
        if (!empty($args['person'])) {
            $query->where('submitted_by', $resolver->member($user, (string) $args['person'])->id);
        }

        return $query;
    }
}
