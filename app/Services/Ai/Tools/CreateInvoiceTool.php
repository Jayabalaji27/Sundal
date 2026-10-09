<?php

namespace App\Services\Ai\Tools;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\DeleteInvoice;
use App\Models\Project;
use App\Models\Tax;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/**
 * A draft invoice billing project tasks, as on the Invoices screen. It is
 * only a draft: sending it is a separate step (send_invoice).
 */
class CreateInvoiceTool extends AiTool implements HasForm
{
    private const MAX_AMOUNT = 1_000_000_000;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly CreateInvoice $createInvoice,
        private readonly DeleteInvoice $deleteInvoice,
    ) {}

    public function name(): string
    {
        return 'create_invoice';
    }

    public function description(): string
    {
        return 'Create a draft invoice for a project, billing some of its tasks (tasks already on a sent or paid invoice cannot be billed again). '
            . 'Each task bills the same amount; the amounts, taxes and items can be changed on the invoice page before sending. '
            . 'Shows the user a confirmation card; nothing is created until they confirm. It is not sent to the client.';
    }

    public function permissions(): array
    {
        return ['invoice_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'tasks' => ['type' => 'string', 'required' => true, 'description' => 'The tasks to bill: titles or ids separated by ";".'],
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Amount billed for each task, as a number.'],
            'title' => ['type' => 'string', 'description' => 'Invoice title. Defaults to "Invoice for <project>".'],
            'client' => ['type' => 'string', 'description' => 'Client name or email. Defaults to the project\'s client when it has exactly one.'],
            'invoice_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to 30 days from today.'],
            'taxes' => ['type' => 'string', 'description' => 'Tax names separated by ";", only if the user asked for taxes.'],
            'notes' => ['type' => 'string', 'description' => 'Notes printed on the invoice.'],
        ];
    }

    public function formTitle(): string
    {
        return __('New invoice');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true, question: __('Which project is the invoice for?')),
            new FormField('tasks', __('Tasks to bill'), 'billable_tasks', required: true, narrowBy: 'project'),
            new FormField('amount', __('Amount per task'), 'number', required: true, question: __('How much should each task bill?'), max: self::MAX_AMOUNT),
            new FormField('invoice_date', __('Invoice date'), 'date', required: true, default: now()->toDateString()),
            new FormField('due_date', __('Due date'), 'date', required: true, default: now()->addDays(30)->toDateString()),
            new FormField('title', __('Title'), 'text'),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $tasks = $this->resolver->many($this->resolver->billableTasks($user, $project->id), (string) ($args['tasks'] ?? ''), 'title', __('unbilled task in this project'));
        if ($tasks->isEmpty()) {
            throw new ToolInputException(__('Which tasks should the invoice bill? Ask the user.'));
        }

        $amount = $this->resolver->number($args['amount'] ?? null, __('Amount per task'), self::MAX_AMOUNT)
            ?? throw new ToolInputException(__('How much should each task bill? Ask the user.'));

        $invoiceDate = $this->resolver->date($args['invoice_date'] ?? null, __('Invoice date')) ?? now()->toDateString();
        $dueDate = $this->resolver->date($args['due_date'] ?? null, __('Due date')) ?? now()->addDays(30)->toDateString();
        if ($dueDate < $invoiceDate) {
            throw new ToolInputException(__('The due date cannot be before the invoice date.'));
        }

        $title = trim((string) ($args['title'] ?? '')) ?: __('Invoice for :project', ['project' => $project->title]);
        if (mb_strlen($title) > 255) {
            throw new ToolInputException(__('The invoice title can be at most 255 characters.'));
        }

        $client = $this->client($project, trim((string) ($args['client'] ?? '')));
        $taxes = $this->taxes($user, (string) ($args['taxes'] ?? ''));

        $subtotal = $amount * $tasks->count();
        $taxAmount = $taxes->sum(fn (Tax $tax) => $subtotal * $tax->rate / 100);
        $money = fn (float $value) => number_format($value, 2);

        return new PreparedAction(
            summary: __('Create a draft invoice for :project (:count tasks, total :total)', ['project' => $project->title, 'count' => $tasks->count(), 'total' => $money($subtotal + $taxAmount)]),
            details: array_filter([
                __('Project') => $project->title,
                __('Title') => $title,
                __('Client') => $client?->name ?? __('None (add one on the invoice page before sending)'),
                __('Amount per task') => $money($amount),
                __('Subtotal') => $money($subtotal),
                __('Taxes') => $taxes->isNotEmpty() ? $taxes->map(fn (Tax $t) => "{$t->name} ({$t->rate}%)")->implode(', ') : null,
                __('Total') => $money($subtotal + $taxAmount),
                __('Invoice date') => $invoiceDate,
                __('Due') => $dueDate,
            ]),
            payload: [
                'project_id' => $project->id,
                'client_id' => $client?->id,
                'title' => $title,
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'notes' => isset($args['notes']) && trim((string) $args['notes']) !== '' ? trim((string) $args['notes']) : null,
                'selected_taxes' => $taxes->pluck('id')->all(),
                'items' => $tasks->map(fn ($task) => ['task_id' => $task->id, 'amount' => $amount])->all(),
            ],
            items: $tasks->map(fn ($task) => "{$task->title} · {$money($amount)}")->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        $billable = $this->resolver->billableTasks($user, $project->id)->whereKey(array_column($payload['items'], 'task_id'))->count();
        if ($billable !== count($payload['items'])) {
            throw new ToolInputException(__('A task on this card was billed or removed since. Start again.'));
        }
        if ($payload['client_id'] && !$project->clients()->whereKey($payload['client_id'])->exists()) {
            throw new ToolInputException(__('The client on this card is no longer on the project.'));
        }

        $invoice = $this->createInvoice->handle($user, [...$payload, 'project_id' => $project->id]);

        return new ToolOutcome(
            __('Created draft invoice :number for :project.', ['number' => $invoice->invoice_number, 'project' => $project->title]),
            $invoice,
            route('invoices.show', $invoice->id, false),
            ['id' => $invoice->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $invoice = $this->resolver->byId($this->resolver->draftInvoices($user), (int) $undo['id'], __('draft invoice'));
        $this->deleteInvoice->handle($user, $invoice);

        return __('Draft invoice :number deleted.', ['number' => $invoice->invoice_number]);
    }

    /** The named client, or the project's only client; null when it has none or several. */
    private function client(Project $project, string $ref): ?User
    {
        $clients = $project->clients();
        if ($ref === '') {
            return $clients->count() === 1 ? $clients->first() : null;
        }

        $match = (clone $clients)->where(fn ($q) => $q->where('users.email', $ref)->orWhere('users.name', 'like', '%' . addcslashes($ref, '%_\\') . '%'))->get();

        return match ($match->count()) {
            1 => $match->first(),
            0 => throw new ToolInputException(__('No client of :project matches ":ref". Ask the user, or leave the client out.', ['project' => $project->title, 'ref' => $ref])),
            default => throw new ToolInputException(__('Several clients match ":ref": :list. Ask the user which one.', ['ref' => $ref, 'list' => $match->pluck('name')->implode(', ')])),
        };
    }

    /** @return \Illuminate\Support\Collection<int, Tax> */
    private function taxes(User $user, string $refs)
    {
        $query = Tax::query()->where('workspace_id', $user->current_workspace_id);

        return $this->resolver->many($query, $refs, 'name', __('tax'));
    }
}
