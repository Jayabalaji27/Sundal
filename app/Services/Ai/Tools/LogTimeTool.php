<?php

namespace App\Services\Ai\Tools;

use App\Actions\Timesheets\DeleteTimeEntry;
use App\Actions\Timesheets\LogTime;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\Ai\Forms\FormField;
use App\Services\Ai\Forms\HasForm;

/** Log the user's own time on a project, as on the Timesheets screen. */
class LogTimeTool extends AiTool implements HasForm
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly LogTime $logTime,
        private readonly DeleteTimeEntry $deleteEntry,
    ) {}

    public function name(): string
    {
        return 'log_time';
    }

    public function description(): string
    {
        return 'Log hours the user worked on a project (and optionally a task) on their own timesheet. '
            . 'Hours are 0.25 to 24, and a day holds at most 24 hours in total. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['timesheet_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'hours' => ['type' => 'number', 'required' => true, 'description' => 'Hours worked, e.g. 2.5.'],
            'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to today.'],
            'task' => ['type' => 'string', 'description' => 'Task title or id in that project.'],
            'description' => ['type' => 'string', 'description' => 'What was done.'],
            'billable' => ['type' => 'boolean', 'description' => 'Defaults to true.'],
        ];
    }

    public function formTitle(): string
    {
        return __('Log time');
    }

    public function formFields(User $user): array
    {
        return [
            new FormField('project', __('Project'), 'project', required: true, question: __('Which project did you work on?')),
            new FormField('hours', __('Hours'), 'number', required: true, question: __('How many hours?'), max: TimesheetEntry::MAX_HOURS_PER_DAY),
            new FormField('date', __('Date'), 'date', required: true, default: now()->toDateString()),
            new FormField('task', __('Task'), 'task', narrowBy: 'project'),
            new FormField('description', __('Description'), 'text', maxLength: 1000),
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $task = !empty($args['task']) ? $this->resolver->task($user, (string) $args['task'], "#{$project->id}") : null;

        $hours = $this->resolver->number($args['hours'] ?? null, __('Hours'), TimesheetEntry::MAX_HOURS_PER_DAY)
            ?? throw new ToolInputException(__('How many hours? Ask the user.'));
        $date = $this->resolver->date($args['date'] ?? null, __('Date')) ?? now()->toDateString();

        $data = [
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'date' => $date,
            'hours' => $hours,
            'description' => isset($args['description']) && trim((string) $args['description']) !== '' ? trim((string) $args['description']) : null,
            'is_billable' => !isset($args['billable']) || filter_var($args['billable'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false,
        ];
        if ($problem = LogTime::hoursProblem($data, $user->id)) {
            throw new ToolInputException($problem[1]);
        }
        if (LogTime::weekTimesheet($user->id, (int) $user->current_workspace_id, $date)->isLocked()) {
            throw new ToolInputException(__('Your timesheet for that week is already submitted or approved, so time cannot be added to it.'));
        }

        return new PreparedAction(
            summary: __('Log :hours h on :project for :date', ['hours' => $hours + 0, 'project' => $project->title, 'date' => $date]),
            details: array_filter([
                __('Project') => $project->title,
                __('Task') => $task?->title,
                __('Hours') => (string) ($hours + 0),
                __('Date') => $date,
                __('Description') => $data['description'],
                __('Billable') => $data['is_billable'] ? __('Yes') : __('No'),
            ]),
            payload: $data,
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        if (!empty($payload['task_id'])) {
            $this->resolver->byId($this->resolver->tasks($user)->where('project_id', $project->id), (int) $payload['task_id'], __('task'));
        }

        $entry = $this->logTime->handle($user, [...$payload, 'project_id' => $project->id]);

        return new ToolOutcome(
            __('Logged :hours h on :project for :date.', ['hours' => (float) $entry->hours + 0, 'project' => $project->title, 'date' => $payload['date']]),
            $entry,
            route('timesheets.show', $entry->timesheet_id, false),
            ['id' => $entry->id],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $entry = $this->resolver->byId($this->resolver->openTimeEntries($user), (int) $undo['id'], __('time entry'));
        $this->deleteEntry->handle($user, $entry);

        return __('Time entry removed.');
    }
}
