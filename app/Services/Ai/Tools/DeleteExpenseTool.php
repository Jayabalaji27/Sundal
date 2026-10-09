<?php

namespace App\Services\Ai\Tools;

use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Delete an expense that is not approved yet (approved ones count against the budget). */
class DeleteExpenseTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DeleteExpense $deleteExpense,
    ) {}

    public function name(): string
    {
        return 'delete_expense';
    }

    public function description(): string
    {
        return 'Delete an expense that is not approved yet. Approved expenses cannot be deleted here. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['expense_delete'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'expense' => ['type' => 'string', 'required' => true, 'description' => 'Expense title or id.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Delete an expense');
    }

    public function formFields(User $user): array
    {
        return [new FormField('expense', __('Expense'), 'expense', required: true, question: __('Which expense should be deleted?'))];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var ProjectExpense $expense */
        $expense = $this->resolver->find($this->resolver->openExpenses($user), (string) ($args['expense'] ?? ''), 'title', __('expense waiting for approval'));
        if (!UpdateExpense::mayChange($user, $expense)) {
            throw new ToolInputException(__('You can only delete your own expenses that have not been approved yet.'));
        }
        $expense->loadMissing('project:id,title');

        return new PreparedAction(
            summary: __('Delete expense ":title"', ['title' => $expense->title]),
            details: array_filter([
                __('Expense') => $expense->title,
                __('Project') => $expense->project?->title,
                __('Amount') => number_format((float) $expense->amount, 2),
                __('Date') => $expense->expense_date?->format('Y-m-d'),
                __('Note') => __('This cannot be undone.'),
            ]),
            payload: ['expense_id' => $expense->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $expense = $this->resolver->byId($this->resolver->openExpenses($user), (int) $payload['expense_id'], __('expense'));
        $title = $expense->title;

        $this->deleteExpense->handle($user, $expense);

        return new ToolOutcome(__('Deleted expense ":title".', ['title' => $title]), null, route('expenses.index', [], false));
    }
}
