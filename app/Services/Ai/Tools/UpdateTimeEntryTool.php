<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timesheets\LogTime;
use App\Actions\Timesheets\UpdateTimeEntry;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Change one of the user's own time entries on a timesheet that is not submitted. */
class UpdateTimeEntryTool extends AiTool implements HasForm
{
    use RevertsChanges;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly UpdateTimeEntry $updateEntry,
    ) {}

    public function name(): string
    {
        return 'update_time_entry';
    }

    public function description(): string
    {
        return 'Change the hours, date or description of one of the user\'s own time entries (not on a submitted or approved timesheet). '
            . 'Use list_my_time to find the entry id. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'entry' => ['type' => 'string', 'required' => true, 'description' => 'Time entry id (e.g. #42), from list_my_time.'],
            'hours' => ['type' => 'number', 'description' => 'New hours.'],
            'date' => ['type' => 'string', 'description' => 'New date, YYYY-MM-DD.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Change a time entry');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('entry', __('Time entry'), 'time_entry', required: true),
            new FormField('hours', __('Hours'), 'number', max: TimesheetEntry::MAX_HOURS_PER_DAY),
            new FormField('date', __('Date'), 'date'),
            new FormField('description', __('Description'), 'text', maxLength: 1000),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var TimesheetEntry $entry */
        $entry = $this->resolver->find($this->resolver->openTimeEntries($user), (string) ($args['entry'] ?? ''), 'description', __('time entry you can change'));

        $changes = array_filter([
            'hours' => $this->resolver->number($args['hours'] ?? null, __('Hours'), TimesheetEntry::MAX_HOURS_PER_DAY),
            'date' => $this->resolver->date($args['date'] ?? null, __('Date')),
            'description' => isset($args['description']) ? trim((string) $args['description']) : null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($changes === []) {
            throw new ToolInputException(__('What should change on this time entry? Ask the user.'));
        }

        $merged = [
            'date' => $entry->date->format('Y-m-d'),
            'hours' => $entry->hours,
            'start_time' => $entry->start_time,
            'end_time' => $entry->end_time,
            ...$changes,
        ];
        if ($problem = LogTime::hoursProblem($merged, $user->id, $entry->id)) {
            throw new ToolInputException($problem[1]);
        }
        if (isset($changes['date']) && LogTime::weekTimesheet($user->id, (int) $user->current_workspace_id, $changes['date'])->isLocked()) {
            throw new ToolInputException(__('Your timesheet for that week is already submitted or approved.'));
        }

        $old = $this->snapshot($entry, array_keys($changes));
        $entry->loadMissing('project:id,title');

        return new PreparedAction(
            summary: __('Change your time entry on :project (:date)', ['project' => $entry->project?->title, 'date' => $entry->date->format('Y-m-d')]),
            details: [
                __('Entry') => "#{$entry->id} · {$entry->project?->title}",
                ...collect($changes)->mapWithKeys(fn ($value, $field) => [__(ucfirst($field)) => ($old[$field] ?? '—') . ' → ' . $value])->all(),
            ],
            payload: ['entry_id' => $entry->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $entry = $this->resolver->byId($this->resolver->openTimeEntries($user), (int) $payload['entry_id'], __('time entry'));
        $before = $this->snapshot($entry, array_keys($payload['changes']));

        $this->updateEntry->handle($user, $entry, $payload['changes']);

        return new ToolOutcome(
            __('Updated your time entry.'),
            $entry,
            route('timesheets.show', $entry->refresh()->timesheet_id, false),
            $this->changeUndo($entry, $before),
        );
    }

    public function undo(array $undo, User $user): string
    {
        $entry = $this->resolver->byId($this->resolver->openTimeEntries($user), (int) $undo['id'], __('time entry'));
        $this->ensureUnchanged($entry, $undo, __('The time entry'));

        $this->updateEntry->handle($user, $entry, $undo['before']);

        return __('Time entry put back.');
    }
}
