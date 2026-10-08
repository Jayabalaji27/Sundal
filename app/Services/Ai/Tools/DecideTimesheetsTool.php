<?php

namespace App\Services\Ai\Tools;

use App\Actions\ActionException;
use App\Actions\Timesheets\DecideTimesheetApproval;
use App\Models\TimesheetApproval;
use App\Models\User;
use App\Models\Workspace;

/**
 * Approve or reject one or many pending timesheets. The card lists every
 * timesheet; more than 10 needs a typed confirmation.
 */
class DecideTimesheetsTool extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DecideTimesheetApproval $decide,
    ) {}

    public function name(): string
    {
        return 'decide_timesheets';
    }

    public function description(): string
    {
        return 'Approve or reject pending timesheets: by approval ids from list_timesheet_approvals, or all pending ones for a person and/or project. Rejecting needs a reason. Shows a confirmation card listing every timesheet.';
    }

    public function permissions(): array
    {
        return ['timesheet_approve'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'decision' => ['type' => 'enum', 'options' => ['approve', 'reject'], 'required' => true, 'description' => 'Approve or reject.'],
            'approval_ids' => ['type' => 'string', 'description' => 'Comma-separated approval ids, e.g. "12,15".'],
            'person' => ['type' => 'string', 'description' => 'All pending timesheets of this person.'],
            'project' => ['type' => 'string', 'description' => 'All pending timesheets with time on this project.'],
            'comments' => ['type' => 'string', 'description' => 'Comment to the person. Required when rejecting.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $decision = ($args['decision'] ?? '') === 'reject' ? 'rejected' : (($args['decision'] ?? '') === 'approve' ? 'approved' : null);
        if (!$decision) {
            throw new ToolInputException(__('Say whether to approve or reject.'));
        }

        $comments = trim((string) ($args['comments'] ?? ''));
        if ($decision === 'rejected' && $comments === '') {
            throw new ToolInputException(__('A reason is required to reject a timesheet. Ask the user for one.'));
        }

        $ids = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($args['approval_ids'] ?? ''))));
        if (!$ids && empty($args['person']) && empty($args['project'])) {
            throw new ToolInputException(__('Which timesheets? Give approval ids, a person or a project.'));
        }

        $query = ListTimesheetApprovals::query($this->resolver, $user, $args)->with('timesheet.user:id,name');
        if ($ids) {
            $query->whereIn('timesheet_approvals.id', $ids);
        }

        $isOwner = Workspace::find($user->current_workspace_id)?->isOwner($user) ?? false;
        $approvals = $query->get()->reject(fn ($a) => !$isOwner && (int) $a->timesheet?->user_id === (int) $user->id);

        if ($approvals->isEmpty()) {
            throw new ToolInputException(__('No pending timesheets match that this user may review.'));
        }

        $verb = $decision === 'approved' ? __('Approve') : __('Reject');
        $count = $approvals->count();

        return new PreparedAction(
            summary: trans_choice(':verb :count timesheet|:verb :count timesheets', $count, ['verb' => $verb, 'count' => $count]),
            details: array_filter([__('Decision') => $verb, __('Comment') => $comments ?: null]),
            payload: ['approval_ids' => $approvals->pluck('id')->values()->all(), 'decision' => $decision, 'comments' => $comments ?: null],
            items: $approvals->map(fn (TimesheetApproval $a) => sprintf(
                '%s — %s to %s — %sh',
                $a->timesheet?->user?->name,
                $a->timesheet?->start_date?->format('Y-m-d'),
                $a->timesheet?->end_date?->format('Y-m-d'),
                (float) $a->timesheet?->total_hours,
            ))->values()->all(),
            confirmPhrase: $count > PreparedAction::BULK_TYPED_CONFIRM_OVER ? strtoupper($verb) . " {$count}" : null,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $approvals = DecideTimesheetApproval::reviewable($user)->whereIn('id', $payload['approval_ids'])->with('timesheet')->get();
        if ($approvals->count() !== count($payload['approval_ids'])) {
            throw new ToolInputException(__('Some timesheets on this card are no longer visible to you. Nothing was changed.'));
        }

        foreach ($approvals as $approval) {
            try {
                $this->decide->handle($user, $approval, $payload['decision'], $payload['comments'] ?? null);
            } catch (ActionException $e) {
                // All or nothing: the surrounding transaction rolls back the rest.
                throw new ToolInputException(__('Timesheet of :name: :error Nothing was changed.', [
                    'name' => $approval->timesheet?->user?->name, 'error' => $e->getMessage(),
                ]));
            }
        }

        $count = $approvals->count();

        return new ToolOutcome(
            $payload['decision'] === 'approved'
                ? trans_choice('Approved :count timesheet.|Approved :count timesheets.', $count, ['count' => $count])
                : trans_choice('Rejected :count timesheet.|Rejected :count timesheets.', $count, ['count' => $count]),
            null,
            route('timesheet-approvals.index', [], false),
        );
    }
}
