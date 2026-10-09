<?php

namespace App\Services\Ai\Tools;

use App\Actions\Budgets\CreateBudget;
use App\Actions\Budgets\UpdateBudget;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Change a project's budget total, period, status or description. Categories stay on the Budgets screen. */
class UpdateBudgetTool extends AiTool implements HasForm
{
    use RevertsChanges;

    private const MAX_AMOUNT = 1_000_000_000_000;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly UpdateBudget $updateBudget,
    ) {}

    public function name(): string
    {
        return 'update_budget';
    }

    public function description(): string
    {
        return 'Change the total, period, status (active, completed, cancelled) or description of a project\'s budget. Only the values given change. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['budget_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'The project whose budget changes: title or id.'],
            'total' => ['type' => 'number', 'description' => 'New total budget.'],
            'period' => ['type' => 'enum', 'options' => CreateBudget::PERIODS, 'description' => 'New period.'],
            'status' => ['type' => 'enum', 'options' => UpdateBudget::STATUSES, 'description' => 'New status.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Change a budget');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true, question: __('Which project\'s budget?')),
            new FormField('total', __('Total budget'), 'number', max: self::MAX_AMOUNT),
            new FormField('period', __('Period'), 'enum', options: FormField::labels(CreateBudget::PERIODS)),
            new FormField('status', __('Status'), 'enum', options: FormField::labels(UpdateBudget::STATUSES)),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        /** @var ProjectBudget|null $budget */
        $budget = $this->resolver->budgets($user)->where('project_id', $project->id)->first()
            ?? throw new ToolInputException(__(':project has no budget yet. Create one with create_budget.', ['project' => $project->title]));

        $changes = array_filter([
            'total_budget' => $this->resolver->number($args['total'] ?? null, __('Total budget'), self::MAX_AMOUNT),
            'period_type' => $args['period'] ?? null,
            'status' => $args['status'] ?? null,
            'description' => isset($args['description']) ? trim((string) $args['description']) : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (isset($changes['period_type']) && !in_array($changes['period_type'], CreateBudget::PERIODS, true)) {
            throw new ToolInputException(__('The period must be project, monthly, quarterly or yearly.'));
        }
        if (isset($changes['status']) && !in_array($changes['status'], UpdateBudget::STATUSES, true)) {
            throw new ToolInputException(__('The status must be active, completed or cancelled.'));
        }
        if ($changes === []) {
            throw new ToolInputException(__('What should change on the budget of :project? Ask the user.', ['project' => $project->title]));
        }

        $old = $this->snapshot($budget, array_keys($changes));
        $labels = ['total_budget' => __('Total budget'), 'period_type' => __('Period'), 'status' => __('Status'), 'description' => __('Description')];
        $show = fn ($field, $value) => $field === 'total_budget' ? number_format((float) $value, 2) : __(ucfirst((string) $value));

        return new PreparedAction(
            summary: __('Change the budget of :project', ['project' => $project->title]),
            details: [
                __('Project') => $project->title,
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [$labels[$field] => $show($field, $old[$field] ?? '—') . ' → ' . $show($field, $value)])->all(),
            ],
            payload: ['budget_id' => $budget->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $budget = $this->resolver->byId($this->resolver->budgets($user), (int) $payload['budget_id'], __('budget'));
        $before = $this->snapshot($budget, array_keys($payload['changes']));

        $this->updateBudget->handle($user, $budget, $payload['changes']);

        return new ToolOutcome(
            __('Updated the budget of :project.', ['project' => $budget->project?->title]),
            $budget,
            route('budgets.show', $budget->id, false),
            $this->changeUndo($budget, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        $budget = $this->resolver->byId($this->resolver->budgets($user), (int) $undo['id'], __('budget'));
        $this->ensureUnchanged($budget, $undo, __('The budget of :project', ['project' => $budget->project?->title]));

        $this->updateBudget->handle($user, $budget, $undo['before']);

        return __('The budget of :project was put back.', ['project' => $budget->project?->title]);
    }
}
