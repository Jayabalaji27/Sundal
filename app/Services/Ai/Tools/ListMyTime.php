<?php

namespace App\Services\Ai\Tools;

use App\Models\Project;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;

/** The user's own time: entries in a date range, their timesheets' status, and the running timer. */
class ListMyTime extends AiTool
{
    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'list_my_time';
    }

    public function description(): string
    {
        return 'List the user\'s own time entries (with ids, for update_time_entry and delete_time_entry), the status of those weeks\' timesheets and whether a timer is running. Defaults to this week.';
    }

    public function permissions(): array
    {
        return ['timesheet_view_any', 'timesheet_create'];
    }

    public function parameters(): array
    {
        return [
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to the start of this week.'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to the end of this week. At most 62 days after from.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $from = $this->resolver->date($args['from'] ?? null, __('From')) ?? now()->startOfWeek()->toDateString();
        $to = $this->resolver->date($args['to'] ?? null, __('To')) ?? Carbon::parse($from)->endOfWeek()->toDateString();
        if ($to < $from || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 62) {
            throw new ToolInputException(__('Use a range of at most 62 days, with "to" after "from".'));
        }

        $query = TimesheetEntry::where('user_id', $user->id)
            ->whereHas('timesheet', fn ($q) => $q->where('workspace_id', $user->current_workspace_id))
            ->whereBetween('date', [$from, $to])
            ->with(['project:id,title', 'task:id,title', 'timesheet:id,status'])
            ->orderBy('date')->orderBy('id');
        if (!empty($args['project'])) {
            $query->where('project_id', $this->resolver->project($user, (string) $args['project'])->id);
        }
        $entries = $query->limit(100)->get();

        $timesheets = Timesheet::where('user_id', $user->id)->where('workspace_id', $user->current_workspace_id)
            ->whereDate('end_date', '>=', $from)->whereDate('start_date', '<=', $to)->orderBy('start_date')->get();

        return [
            'from' => $from,
            'to' => $to,
            'total_hours' => round((float) $entries->sum('hours'), 2),
            'entries' => $entries->map(fn (TimesheetEntry $e) => [
                'id' => $e->id,
                'date' => $e->date->format('Y-m-d'),
                'hours' => (float) $e->hours,
                'project' => $e->project?->title,
                'task' => $e->task?->title,
                'description' => $e->description,
                'billable' => (bool) $e->is_billable,
                'can_change' => !in_array($e->timesheet?->status, ['submitted', 'approved'], true),
            ])->all(),
            'timesheets' => $timesheets->map(fn (Timesheet $t) => [
                'id' => $t->id,
                'week' => $t->start_date->format('Y-m-d') . ' – ' . $t->end_date->format('Y-m-d'),
                'status' => $t->status,
                'total_hours' => (float) $t->total_hours,
                'link' => route('timesheets.show', $t->id, false),
            ])->all(),
            'timer' => $user->timer_active ? [
                'project' => Project::whereKey($user->timer_project_id)->value('title'),
                'started_at' => $user->timer_started_at ? Carbon::parse($user->timer_started_at)->format('Y-m-d H:i') : null,
            ] : null,
        ];
    }
}
