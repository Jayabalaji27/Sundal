<?php

namespace App\Services\Ai\Tools;

use App\Models\Contract;
use App\Models\User;

/**
 * Read only. Creating a contract stays on the Contracts screen: its form
 * requires an uploaded contract file, which the assistant cannot provide.
 */
class ListContracts extends AiTool
{
    public function name(): string
    {
        return 'list_contracts';
    }

    public function description(): string
    {
        return 'List contracts with client, type, value, dates and status; can show ones expiring soon. Contracts cannot be created from chat (they need an uploaded file); point the user to the Contracts screen for that.';
    }

    public function permissions(): array
    {
        return ['contract_view_any'];
    }

    public function parameters(): array
    {
        return [
            'status' => ['type' => 'enum', 'options' => ['pending', 'sent', 'accept', 'decline', 'expired'], 'description' => 'Contract status.'],
            'expiring_within_days' => ['type' => 'number', 'description' => 'Only contracts ending between today and this many days from now.'],
            'search' => ['type' => 'string', 'description' => 'Part of the subject or client name.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $query = Contract::query()
            ->where('contracts.workspace_id', $user->current_workspace_id)
            ->with(['contractType:id,name', 'client:id,name']);

        if (!empty($args['status'])) {
            $query->where('status', $args['status']);
        }
        if (!empty($args['expiring_within_days'])) {
            $days = max(0, min(365, (int) $args['expiring_within_days']));
            $query->whereBetween('end_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
        }
        if (!empty($args['search'])) {
            $like = '%' . addcslashes($args['search'], '%_\\') . '%';
            $query->where(fn ($q) => $q->where('subject', 'like', $like)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)));
        }

        $contracts = $query->orderBy('end_date')->limit(30)->get();

        return [
            'contracts' => $contracts->map(fn (Contract $c) => [
                'id' => $c->id,
                'subject' => $c->subject,
                'type' => $c->contractType?->name,
                'client' => $c->client?->name,
                'value' => (float) $c->contract_value,
                'currency' => $c->currency,
                'start_date' => $c->start_date?->format('Y-m-d'),
                'end_date' => $c->end_date?->format('Y-m-d'),
                'status' => $c->status,
                'link' => route('contracts.show', $c->id, false),
            ])->all(),
        ];
    }
}
