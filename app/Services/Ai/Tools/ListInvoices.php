<?php

namespace App\Services\Ai\Tools;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_invoices';
    }

    public function description(): string
    {
        return 'List invoices the user can see, with number, client, project, total, amount paid, balance due, status and due date. Filter by status, project, client, unpaid or overdue.';
    }

    public function permissions(): array
    {
        return ['invoice_view_any'];
    }

    public function parameters(): array
    {
        return [
            'status' => ['type' => 'enum', 'options' => ['draft', 'sent', 'viewed', 'paid', 'partial_paid', 'overdue', 'cancelled'], 'description' => 'Invoice status.'],
            'unpaid' => ['type' => 'boolean', 'description' => 'Only sent, viewed, partly paid or overdue invoices.'],
            'overdue' => ['type' => 'boolean', 'description' => 'Only unpaid invoices past their due date.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'search' => ['type' => 'string', 'description' => 'Invoice number or title.'],
        ];
    }

    /** Invoices this user may see, by the same rule as the Invoices screen. */
    public static function visible(User $user): Builder
    {
        $workspace = Workspace::find($user->current_workspace_id);
        $role = $workspace?->isOwner($user) ? 'owner' : $workspace?->getMemberRole($user);

        return Invoice::query()->where('workspace_id', $user->current_workspace_id)->visibleToRole($user, $role);
    }

    public function run(array $args, User $user): array
    {
        $query = self::visible($user)->with(['client:id,name', 'project:id,title']);

        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }
        if (!empty($args['unpaid']) || !empty($args['overdue'])) {
            $query->whereIn('status', ['sent', 'viewed', 'partial_paid', 'overdue']);
        }
        if (!empty($args['overdue'])) {
            $query->whereDate('due_date', '<', now()->toDateString());
        }
        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, (string) $args['project'])->id);
        }
        if (!empty($args['search'])) {
            $like = '%' . addcslashes($args['search'], '%_\\') . '%';
            $query->where(fn ($q) => $q->where('invoice_number', 'like', $like)->orWhere('title', 'like', $like));
        }

        $invoices = $query->latest('id')->limit(30)->get();

        return [
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->invoice_number,
                'title' => $i->title,
                'client' => $i->client?->name,
                'project' => $i->project?->title,
                'total' => (float) $i->total_amount,
                'paid' => (float) $i->paid_amount,
                'balance_due' => (float) $i->total_amount - (float) $i->paid_amount,
                'status' => $i->status,
                'due_date' => $i->due_date?->format('Y-m-d'),
                'link' => route('invoices.show', $i->id, false),
            ])->all(),
            'shown' => $invoices->count(),
        ];
    }
}
