<?php

namespace App\Services\Ai\Tools;

use App\Models\Task;
use App\Models\User;
use App\Services\MeetingDirectory;
use Carbon\Carbon;

/**
 * The Calendar page for a date range: task and bug deadlines and meetings,
 * day by day, with the calendar's own visibility (Task::visibleOnCalendarTo,
 * MeetingDirectory).
 */
class GetCalendar extends AiTool
{
    private const MAX_DAYS = 62;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MeetingDirectory $meetings,
    ) {}

    public function name(): string
    {
        return 'get_calendar';
    }

    public function description(): string
    {
        return 'The calendar for a date range: task and bug deadlines and meetings, day by day. Use it for "what is due this week?", "what is on my calendar tomorrow?". Filter by project or by the person the work is assigned to.';
    }

    public function permissions(): array
    {
        return ['task_calendar_view'];
    }

    public function parameters(): array
    {
        return [
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to 7 days after the start; at most 62 days after it.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'assignee' => ['type' => 'string', 'description' => 'Deadlines only: "me" or a person\'s name or email.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $from = $this->resolver->date($args['from'] ?? null, __('From')) ?? now()->toDateString();
        $to = $this->resolver->date($args['to'] ?? null, __('To')) ?? Carbon::parse($from)->addDays(7)->toDateString();
        if ($to < $from) {
            throw new ToolInputException(__('The end of the period cannot be before its start.'));
        }
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > self::MAX_DAYS) {
            throw new ToolInputException(__('Ask for at most :days days at a time.', ['days' => self::MAX_DAYS]));
        }
        $project = !empty($args['project']) ? $this->resolver->project($user, (string) $args['project']) : null;
        $assignee = !empty($args['assignee']) ? $this->resolver->member($user, (string) $args['assignee']) : null;

        $deadlines = Task::with(['project:id,title', 'taskStage:id,name,is_completed', 'assignedTo:id,name'])
            ->visibleOnCalendarTo($user)
            ->whereBetween('end_date', [$from, $to])
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->when($assignee, fn ($q) => $q->where('assigned_to', $assignee->id))
            ->orderBy('end_date')
            ->limit(100)
            ->get()
            ->map(fn (Task $t) => array_filter([
                'date' => $t->end_date->toDateString(),
                'kind' => $t->isBug() ? 'bug due' : 'task due',
                'title' => $t->title,
                'project' => $t->project?->title,
                'assignee' => $t->assignedTo?->name,
                'stage' => $t->taskStage?->name,
                'done' => (bool) $t->taskStage?->is_completed,
                'priority' => $t->priority,
                'link' => route('tasks.show', $t->id, false),
            ], fn ($v) => $v !== null));

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();
        $meetings = $this->meetings->forUser($user, $project?->id)
            ->filter(fn (array $m) => $m['status'] !== 'cancelled' && $m['start_time']
                && Carbon::parse($m['start_time'])->between($start, $end))
            ->map(fn (array $m) => array_filter([
                'date' => Carbon::parse($m['start_time'])->timezone(config('app.timezone'))->toDateString(),
                'time' => Carbon::parse($m['start_time'])->timezone(config('app.timezone'))->format('H:i'),
                'kind' => $m['provider'] === 'zoom' ? 'Zoom meeting' : 'Google Meet',
                'title' => $m['title'],
                'project' => $m['project'],
                'minutes' => $m['duration'],
                'link' => parse_url($m['show_url'], PHP_URL_PATH),
            ], fn ($v) => $v !== null));

        $days = $deadlines->concat($meetings)
            ->sortBy(fn ($e) => $e['date'] . ($e['time'] ?? '00:00'))
            ->groupBy('date')
            ->map(fn ($events) => $events->map(fn ($e) => collect($e)->except('date')->all())->values()->all())
            ->all();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'deadlines' => $deadlines->count(),
            'meetings' => $meetings->count(),
            'link' => route('task-calendar.index', [], false),
        ];
    }
}
