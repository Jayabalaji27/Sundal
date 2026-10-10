<?php

namespace App\Services\Ai\Tools;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Submit a project expense for approval, as on the Expenses screen. */
class CreateExpenseTool extends AiTool implements HasForm
{
    private const MAX_AMOUNT = 1_000_000_000;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateExpense $createExpense,
        private readonly DeleteExpense $deleteExpense,
    ) {}

    public function name(): string
    {
        return 'create_expense';
    }

    public function description(): string
    {
        return 'Record a project expense; it waits for approval. The date cannot be in the future. Shows the user a confirmation card; nothing is saved until they confirm.';
    }

    public function permissions(): array
    {
        return ['expense_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'What was paid for, e.g. "Hosting for October". At most 255 characters.'],
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Amount spent, as a number.'],
            'expense_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, not in the future. Defaults to today.'],
            'category' => ['type' => 'string', 'description' => 'Budget category name of the project, e.g. Development.'],
            'description' => ['type' => 'string', 'description' => 'More detail.'],
        ];
    }

    public function formTitle(): string
    {
        return __('New expense');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('title', __('Title'), 'text', required: true, question: __('What was the expense for?')),
            new FormField('project', __('Project'), 'project', required: true),
            new FormField('amount', __('Amount'), 'number', required: true, question: __('How much was it?'), max: self::MAX_AMOUNT),
            new FormField('expense_date', __('Date'), 'date', required: true, default: now()->toDateString()),
            new FormField('category', __('Budget category'), 'budget_category', narrowBy: 'project'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));

        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The expense needs a title of at most 255 characters.'));
        }

        $amount = $this->resolver->number($args['amount'] ?? null, __('Amount'), self::MAX_AMOUNT)
            ?? throw new ToolInputException(__('How much was the expense? Ask the user.'));

        $date = $this->resolver->date($args['expense_date'] ?? null, __('Date')) ?? now()->toDateString();
        if ($date > now()->toDateString()) {
            throw new ToolInputException(__('The expense date cannot be in the future.'));
        }

        $category = !empty($args['category'])
            ? $this->resolver->find($this->resolver->budgetCategories($user, $project->id), (string) $args['category'], 'name', __('budget category of :project', ['project' => $project->title]))
            : null;
        $description = isset($args['description']) && trim((string) $args['description']) !== '' ? trim((string) $args['description']) : null;

        return new PreparedAction(
            summary: __('Record expense ":title" (:amount) in :project', ['title' => $title, 'amount' => number_format($amount, 2), 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Title') => $title,
                __('Amount') => number_format($amount, 2),
                __('Date') => $date,
                __('Budget category') => $category?->name,
                __('Status') => __('Waiting for approval'),
            ]),
            payload: [
                'project_id' => $project->id,
                'budget_category_id' => $category?->id,
                'title' => $title,
                'amount' => $amount,
                'expense_date' => $date,
                'description' => $description,
            ],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        if (!empty($payload['budget_category_id'])) {
            $this->resolver->byId($this->resolver->budgetCategories($user, $project->id), (int) $payload['budget_category_id'], __('budget category'));
        }

        $expense = $this->createExpense->handle($user, [...$payload, 'project_id' => $project->id]);

        return new ToolOutcome(
            __('Recorded expense ":title" in :project; it waits for approval.', ['title' => $expense->title, 'project' => $project->title]),
            $expense,
            route('expenses.show', $expense->id, false),
            ['id' => $expense->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $expense = $this->resolver->byId($this->resolver->openExpenses($user)->where('project_expenses.status', 'pending'), (int) $undo['id'], __('pending expense'));
        $this->deleteExpense->handle($user, $expense);

        return __('Expense ":title" removed.', ['title' => $expense->title]);
    }
}
