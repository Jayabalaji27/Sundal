<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timesheets\SubmitTimesheet;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Send the user's own timesheet for approval. It is locked until approved or rejected. */
class SubmitTimesheetTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly SubmitTimesheet $submitTimesheet,
    ) {}

    public function name(): string
    {
        return 'submit_timesheet';
    }

    public function description(): string
    {
        return 'Submit one of the user\'s own draft or rejected timesheets for approval. After that its time cannot be changed until it is approved or rejected. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_submit'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'week' => ['type' => 'string', 'description' => 'Any date in the week, YYYY-MM-DD. Leave out when the user has only one timesheet to submit.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Submit a timesheet');
    }

    public function formFields(User $user): array
    {
        return [new FormField('week', __('Timesheet'), 'timesheet', required: true, question: __('Which week should be submitted?'))];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $timesheet = $this->timesheet($user, trim((string) ($args['week'] ?? '')));
        $timesheet->loadCount('entries');

        return new PreparedAction(
            summary: __('Submit your timesheet for the week of :date', ['date' => $timesheet->start_date->format('Y-m-d')]),
            details: [
                __('Week') => $timesheet->start_date->format('Y-m-d') . ' – ' . $timesheet->end_date->format('Y-m-d'),
                __('Entries') => (string) $timesheet->entries_count,
                __('Total hours') => (string) ((float) $timesheet->total_hours + 0),
                __('Note') => __('The time cannot be changed while it waits for approval.'),
            ],
            payload: ['timesheet_id' => $timesheet->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $timesheet = $this->resolver->byId($this->resolver->openTimesheets($user), (int) $payload['timesheet_id'], __('timesheet'));

        $this->submitTimesheet->handle($user, $timesheet);

        return new ToolOutcome(
            __('Submitted your timesheet for the week of :date for approval.', ['date' => $timesheet->start_date->format('Y-m-d')]),
            $timesheet,
            route('timesheets.show', $timesheet->id, false),
        );
    }

    /** "#12", a date in the week, or nothing when there is exactly one to submit. */
    private function timesheet(User $user, string $ref): Timesheet
    {
        $open = $this->resolver->openTimesheets($user);

        if (preg_match('/^#?(\d+)$/', $ref, $m) && ($byId = (clone $open)->whereKey((int) $m[1])->first())) {
            return $byId;
        }
        if ($ref !== '') {
            $date = $this->resolver->date($ref, __('Week'));

            return (clone $open)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->first()
                ?? throw new ToolInputException(__('There is no draft or rejected timesheet with entries for the week of :date.', ['date' => $date]));
        }

        $all = (clone $open)->orderByDesc('start_date')->limit(6)->get();

        return match ($all->count()) {
            1 => $all->first(),
            0 => throw new ToolInputException(__('There is no draft or rejected timesheet with entries to submit.')),
            default => throw new ToolInputException(__('Several timesheets can be submitted (weeks of :list). Ask the user which week.', [
                'list' => $all->map(fn (Timesheet $t) => $t->start_date->format('Y-m-d'))->implode(', '),
            ])),
        };
    }
}
