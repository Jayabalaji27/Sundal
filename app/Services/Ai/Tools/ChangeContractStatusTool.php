<?php

namespace App\Services\Ai\Tools;

use App\Models\Contract;
use App\Models\User;

/**
 * Changes a contract's status (pending, sent, accepted, declined, expired),
 * as the status menu on the contract does for the workspace owner. Creating
 * a contract stays on the Contracts screen (it needs the contract file).
 */
class ChangeContractStatusTool extends AiTool
{
    private const LABELS = ['pending' => 'Pending', 'sent' => 'Sent', 'accept' => 'Accepted', 'decline' => 'Declined', 'expired' => 'Expired'];

    /** When each status was first reached. */
    private const STAMPS = ['sent' => 'sent_at', 'accept' => 'accepted_at', 'decline' => 'declined_at'];

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'change_contract_status';
    }

    public function description(): string
    {
        return 'Change a contract\'s status: pending, sent (to the client), accept, decline or expired. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['contract_change_status'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'contract' => ['type' => 'string', 'required' => true, 'description' => 'Contract subject or id.'],
            'status' => ['type' => 'enum', 'options' => array_keys(self::LABELS), 'required' => true, 'description' => 'New status.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var Contract $contract */
        $contract = $this->resolver->contract($user, (string) ($args['contract'] ?? ''));
        $status = $args['status'] ?? '';
        if (!array_key_exists($status, self::LABELS)) {
            throw new ToolInputException(__('Choose pending, sent, accept, decline or expired.'));
        }
        if ($contract->status === $status) {
            throw new ToolInputException(__('Contract ":subject" is already :status.', ['subject' => $contract->subject, 'status' => mb_strtolower(self::LABELS[$status])]));
        }

        return new PreparedAction(
            summary: __('Mark contract ":subject" as :status', ['subject' => $contract->subject, 'status' => mb_strtolower(__(self::LABELS[$status]))]),
            details: array_filter([
                __('Contract') => $contract->subject,
                __('Client') => $contract->client?->name,
                __('Status') => __(self::LABELS[$contract->status] ?? $contract->status) . ' → ' . __(self::LABELS[$status]),
            ]),
            payload: ['contract_id' => $contract->id, 'status' => $status],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        /** @var Contract $contract */
        $contract = $this->resolver->byId($this->resolver->contracts($user), (int) $payload['contract_id'], __('contract'));
        $status = $payload['status'];
        $undo = ['contract_id' => $contract->id, 'from' => $contract->status, 'to' => $status, 'stamp' => null];

        $updates = ['status' => $status];
        if (($stamp = self::STAMPS[$status] ?? null) && !$contract->{$stamp}) {
            $updates[$stamp] = now();
            $undo['stamp'] = $stamp;
        }
        $contract->update($updates);

        return new ToolOutcome(
            __('Contract ":subject" is now :status.', ['subject' => $contract->subject, 'status' => mb_strtolower(__(self::LABELS[$status]))]),
            $contract,
            route('contracts.show', $contract->id, false),
            $undo,
        );
    }

    public function undo(array $undo, User $user): string
    {
        /** @var Contract $contract */
        $contract = $this->resolver->byId($this->resolver->contracts($user), (int) $undo['contract_id'], __('contract'));
        if ($contract->status !== $undo['to']) {
            throw new ToolInputException(__('Contract ":subject" was changed again since, so it was not undone.', ['subject' => $contract->subject]));
        }

        $contract->update(['status' => $undo['from'], ...($undo['stamp'] ? [$undo['stamp'] => null] : [])]);

        return __('Contract ":subject" is :status again.', ['subject' => $contract->subject, 'status' => mb_strtolower(__(self::LABELS[$undo['from']] ?? $undo['from']))]);
    }
}
