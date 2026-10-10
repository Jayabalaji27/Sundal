<?php

namespace App\Services\Ai\Tools;

use App\Actions\Expenses\UpdateExpense;
use App\Models\ProjectExpense;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Change an expense that is not approved yet. */
class UpdateExpenseTool extends AiTool implements HasForm
{
    use RevertsChanges;

    private const MAX_AMOUNT = 1_000_000_000;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly UpdateExpense $updateExpense,
    ) {}

    public function name(): string
    {
        return 'update_expense';
    }

    public function description(): string
    {
        return 'Change the title, amount, date or description of an expense that is not approved yet. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['expense_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'expense' => ['type' => 'string', 'required' => true, 'description' => 'Expense title or id.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
            'amount' => ['type' => 'number', 'description' => 'New amount.'],
            'expense_date' => ['type' => 'string', 'description' => 'New date, YYYY-MM-DD, not in the future.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Change an expense');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('expense', __('Expense'), 'expense', required: true),
            new FormField('title', __('Title'), 'text'),
            new FormField('amount', __('Amount'), 'number', max: self::MAX_AMOUNT),
            new FormField('expense_date', __('Date'), 'date'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var ProjectExpense $expense */
        $expense = $this->resolver->find($this->resolver->openExpenses($user), (string) ($args['expense'] ?? ''), 'title', __('expense waiting for approval'));

        $changes = array_filter([
            'title' => isset($args['title']) ? trim((string) $args['title']) : null,
            'amount' => $this->resolver->number($args['amount'] ?? null, __('Amount'), self::MAX_AMOUNT),
            'expense_date' => $this->resolver->date($args['expense_date'] ?? null, __('Date')),
            'description' => isset($args['description']) ? trim((string) $args['description']) : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($changes === []) {
            throw new ToolInputException(__('What should change on expense ":title"? Ask the user.', ['title' => $expense->title]));
        }
        if (isset($changes['title']) && mb_strlen($changes['title']) > 255) {
            throw new ToolInputException(__('The expense needs a title of at most 255 characters.'));
        }
        if (isset($changes['expense_date']) && $changes['expense_date'] > now()->toDateString()) {
            throw new ToolInputException(__('The expense date cannot be in the future.'));
        }
        if (!UpdateExpense::mayChange($user, $expense)) {
            throw new ToolInputException(__('You can only edit your own expenses that have not been approved yet.'));
        }

        $old = $this->snapshot($expense, array_keys($changes));
        $show = fn ($field, $value) => $field === 'amount' ? number_format((float) $value, 2) : (string) $value;

        return new PreparedAction(
            summary: __('Change expense ":title"', ['title' => $expense->title]),
            details: [
                __('Expense') => $expense->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [
                    __(ucfirst(str_replace(['expense_', '_'], ['', ' '], $field))) => $show($field, $old[$field] ?? '—') . ' → ' . $show($field, $value),
                ])->all(),
            ],
            payload: ['expense_id' => $expense->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $expense = $this->resolver->byId($this->resolver->openExpenses($user), (int) $payload['expense_id'], __('expense'));
        $before = $this->snapshot($expense, array_keys($payload['changes']));

        $this->updateExpense->handle($user, $expense, $payload['changes']);

        return new ToolOutcome(
            __('Updated expense ":title".', ['title' => $expense->title]),
            $expense,
            route('expenses.show', $expense->id, false),
            $this->changeUndo($expense, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        $expense = $this->resolver->byId($this->resolver->openExpenses($user), (int) $undo['id'], __('expense'));
        $this->ensureUnchanged($expense, $undo, __('Expense ":title"', ['title' => $expense->title]));

        $this->updateExpense->handle($user, $expense, $undo['before']);

        return __('Expense ":title" put back.', ['title' => $expense->title]);
    }
}
