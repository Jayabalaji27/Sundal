<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timesheets\DecideTimesheetApproval;
use App\Models\TimesheetApproval;
use App\Models\User;

class ListTimesheetApprovals extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_timesheet_approvals';
    }

    public function description(): string
    {
        return 'List timesheets waiting for approval (or already decided) that this user may review, with person, week and hours. Use the ids with decide_timesheets.';
    }

    public function permissions(): array
    {
        return ['timesheet_approve'];
    }

    public function parameters(): array
    {
        return [
            'status' => ['type' => 'enum', 'options' => ['pending', 'approved', 'rejected'], 'description' => 'Defaults to pending.'],
            'person' => ['type' => 'string', 'description' => 'Whose timesheets: a name or email.'],
            'project' => ['type' => 'string', 'description' => 'Only timesheets with time logged on this project.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $approvals = $this->query($this->resolver, $user, $args)
            ->with(['timesheet.user:id,name'])
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'timesheet_approvals' => $approvals->map(fn (TimesheetApproval $a) => [
                'approval_id' => $a->id,
                'person' => $a->timesheet?->user?->name,
                'period' => $a->timesheet?->start_date?->format('Y-m-d') . ' to ' . $a->timesheet?->end_date?->format('Y-m-d'),
                'total_hours' => (float) $a->timesheet?->total_hours,
                'billable_hours' => (float) $a->timesheet?->billable_hours,
                'status' => $a->status,
            ])->all(),
            'shown' => $approvals->count(),
        ];
    }

    /** Shared with DecideTimesheetsTool so both see exactly the same set. */
    public static function query(RecordResolver $resolver, User $user, array $args)
    {
        $query = DecideTimesheetApproval::reviewable($user)->where('status', $args['status'] ?? 'pending');

        if (!empty($args['person'])) {
            $person = $resolver->member($user, (string) $args['person']);
            $query->whereHas('timesheet', fn ($q) => $q->where('user_id', $person->id));
        }
        if (!empty($args['project'])) {
            $project = $resolver->project($user, (string) $args['project']);
            $query->whereHas('timesheet.entries', fn ($q) => $q->where('project_id', $project->id));
        }

        return $query;
    }
}
