<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timesheets\DeleteTimeEntry;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Delete one of the user's own time entries on a timesheet that is not submitted. */
class DeleteTimeEntryTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly DeleteTimeEntry $deleteEntry,
    ) {}

    public function name(): string
    {
        return 'delete_time_entry';
    }

    public function description(): string
    {
        return 'Delete one of the user\'s own time entries (not on a submitted or approved timesheet). Use list_my_time to find the entry id. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_delete'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'entry' => ['type' => 'string', 'required' => true, 'description' => 'Time entry id (e.g. #42), from list_my_time.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Delete a time entry');
    }

    public function formFields(User $user): array
    {
        return [new FormField('entry', __('Time entry'), 'time_entry', required: true, question: __('Which time entry should be deleted?'))];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        /** @var TimesheetEntry $entry */
        $entry = $this->resolver->find($this->resolver->openTimeEntries($user), (string) ($args['entry'] ?? ''), 'description', __('time entry you can change'));
        $entry->loadMissing('project:id,title', 'task:id,title');

        return new PreparedAction(
            summary: __('Delete your time entry on :project (:date)', ['project' => $entry->project?->title, 'date' => $entry->date->format('Y-m-d')]),
            details: array_filter([
                __('Project') => $entry->project?->title,
                __('Task') => $entry->task?->title,
                __('Date') => $entry->date->format('Y-m-d'),
                __('Hours') => (string) ((float) $entry->hours + 0),
                __('Description') => $entry->description,
                __('Note') => __('This cannot be undone.'),
            ]),
            payload: ['entry_id' => $entry->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $entry = $this->resolver->byId($this->resolver->openTimeEntries($user), (int) $payload['entry_id'], __('time entry'));
        $timesheetId = $entry->timesheet_id;

        $this->deleteEntry->handle($user, $entry);

        return new ToolOutcome(__('Deleted your time entry.'), null, route('timesheets.show', $timesheetId, false));
    }
}
