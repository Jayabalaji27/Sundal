<?php

namespace App\Actions\Timesheets;

use App\Actions\ActionException;
use App\Models\Timesheet;
use App\Models\TimesheetApproval;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Approves or rejects one timesheet approval and settles the timesheet's
 * status. Shared by TimesheetApprovalController and the AI assistant.
 */
class DecideTimesheetApproval
{
    /**
     * Approvals the actor may decide: owners see the whole workspace, managers
     * only timesheets with entries on projects they created or are members of.
     */
    public static function reviewable(User $actor): Builder
    {
        $workspace = Workspace::find($actor->current_workspace_id);

        $query = TimesheetApproval::query()
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $actor->current_workspace_id));

        if ($workspace && !$workspace->isOwner($actor)) {
            $query->whereHas('timesheet.entries.project', fn ($p) => $p->where(function ($pq) use ($actor) {
                $pq->whereHas('members', fn ($m) => $m->where('user_id', $actor->id))
                    ->orWhere('created_by', $actor->id);
            }));
        }

        return $query;
    }

    public function handle(User $actor, TimesheetApproval $approval, string $decision, ?string $comments = null): TimesheetApproval
    {
        $workspace = Workspace::find($actor->current_workspace_id);
        $isOwner = $workspace?->isOwner($actor) ?? false;

        if (!$isOwner && $workspace?->getMemberRole($actor) !== 'manager') {
            throw new ActionException(__('Only owners and managers can approve timesheets.'));
        }
        if ($approval->status !== 'pending') {
            throw new ActionException(__('This approval has already been processed.'));
        }
        if (!$isOwner && (int) $approval->timesheet?->user_id === (int) $actor->id) {
            throw new ActionException(__('You cannot approve or reject your own timesheet. The workspace owner reviews it.'));
        }
        if ($decision === 'rejected' && trim((string) $comments) === '') {
            throw new ActionException(__('A reason is required to reject a timesheet.'));
        }

        $approval->update([
            'status' => $decision === 'rejected' ? 'rejected' : 'approved',
            'comments' => $comments,
            'approved_at' => now(),
        ]);

        // One rejection rejects the timesheet straight away; an approval only
        // settles it once every approver in the current round has decided.
        $decision === 'rejected'
            ? $approval->timesheet->update(['status' => 'rejected'])
            : $this->settleTimesheet($approval->timesheet, $actor);

        return $approval;
    }

    /**
     * Only each approver's newest decision counts. A resubmitted timesheet keeps
     * its earlier rejection as history, which otherwise flipped it straight back
     * to rejected when the new round was approved.
     */
    public function settleTimesheet(Timesheet $timesheet, User $actor): void
    {
        $currentRound = $timesheet->approvals()
            ->whereIn('id', fn ($q) => $q->selectRaw('MAX(id)')->from('timesheet_approvals')
                ->where('timesheet_id', $timesheet->id)->groupBy('approver_id'))
            ->pluck('status');

        if ($currentRound->contains('pending')) {
            return;
        }

        if ($currentRound->contains('rejected')) {
            $timesheet->update(['status' => 'rejected']);
        } else {
            $timesheet->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id]);
        }
    }
}
