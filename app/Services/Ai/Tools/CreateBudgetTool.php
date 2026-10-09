<?php

namespace App\Services\Ai\Tools;

use App\Actions\Budgets\CreateBudget;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/**
 * A project's budget. The whole amount goes into one "General" category;
 * splitting it into categories is done on the Budgets screen.
 */
class CreateBudgetTool extends AiTool implements HasForm
{
    private const MAX_AMOUNT = 1_000_000_000_000;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateBudget $createBudget,
    ) {}

    public function name(): string
    {
        return 'create_budget';
    }

    public function description(): string
    {
        return 'Create the budget for a project (a project has at most one). The whole amount goes into one "General" category, which can be split on the Budgets screen. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['budget_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'total' => ['type' => 'number', 'required' => true, 'description' => 'Total budget, as a number.'],
            'period' => ['type' => 'enum', 'options' => CreateBudget::PERIODS, 'description' => 'Defaults to project (the whole project).'],
            'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, after the start date.'],
            'description' => ['type' => 'string', 'description' => 'What the budget covers.'],
        ];
    }

    public function formTitle(): string
    {
        return __('New budget');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true, question: __('Which project is the budget for?')),
            new FormField('total', __('Total budget'), 'number', required: true, question: __('How much is the budget?'), max: self::MAX_AMOUNT),
            new FormField('period', __('Period'), 'enum', required: true, options: FormField::labels(CreateBudget::PERIODS), default: 'project'),
            new FormField('start_date', __('Start date'), 'date', required: true, default: now()->toDateString()),
            new FormField('end_date', __('End date'), 'date'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        if (CreateBudget::existingFor($project->id)) {
            throw new ToolInputException(__(':project already has a budget. Change that one with update_budget.', ['project' => $project->title]));
        }

        $total = $this->resolver->number($args['total'] ?? null, __('Total budget'), self::MAX_AMOUNT)
            ?? throw new ToolInputException(__('How much is the budget? Ask the user.'));

        $period = $args['period'] ?? 'project';
        if (!in_array($period, CreateBudget::PERIODS, true)) {
            throw new ToolInputException(__('The period must be project, monthly, quarterly or yearly.'));
        }

        $start = $this->resolver->date($args['start_date'] ?? null, __('Start date')) ?? now()->toDateString();
        $end = $this->resolver->date($args['end_date'] ?? null, __('End date'));
        if ($end !== null && ($end <= $start || $end < now()->toDateString())) {
            throw new ToolInputException(__('The end date must be after the start date and not in the past.'));
        }
        $description = isset($args['description']) && trim((string) $args['description']) !== '' ? trim((string) $args['description']) : null;

        return new PreparedAction(
            summary: __('Create a budget of :total for :project', ['total' => number_format($total, 2), 'project' => $project->title]),
            details: array_filter([
                __('Project') => $project->title,
                __('Total budget') => number_format($total, 2),
                __('Period') => __(ucfirst($period)),
                __('Start date') => $start,
                __('End date') => $end,
                __('Categories') => __('General (the whole amount)'),
            ]),
            payload: [
                'project_id' => $project->id,
                'total_budget' => $total,
                'period_type' => $period,
                'start_date' => $start,
                'end_date' => $end,
                'description' => $description,
            ],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));

        $budget = $this->createBudget->handle($user, [
            ...$payload,
            'project_id' => $project->id,
            'categories' => [['name' => __('General'), 'allocated_amount' => $payload['total_budget'], 'description' => __('Created by the AI Assistant')]],
        ]);

        return new ToolOutcome(
            __('Created a budget of :total for :project.', ['total' => number_format((float) $payload['total_budget'], 2), 'project' => $project->title]),
            $budget,
            route('budgets.show', $budget->id, false),
        );
    }
}
