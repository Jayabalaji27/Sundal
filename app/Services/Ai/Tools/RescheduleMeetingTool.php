<?php

namespace App\Services\Ai\Tools;

use App\Actions\Meetings\RescheduleMeeting;
use App\Models\User;
use App\Services\MeetingDirectory;
use Carbon\Carbon;

/** Moves an upcoming Zoom or Google Meet meeting to another time, changes its length or renames it. */
class RescheduleMeetingTool extends AiTool
{
    use FindsMeetings;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MeetingDirectory $meetings,
        private readonly RescheduleMeeting $reschedule,
    ) {}

    protected function directory(): MeetingDirectory
    {
        return $this->meetings;
    }

    public function name(): string
    {
        return 'reschedule_meeting';
    }

    public function description(): string
    {
        return 'Move an upcoming Zoom or Google Meet meeting to a new date and time, change how long it is, or rename it. Only the person who scheduled it or the workspace owner can. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['zoom_meeting_update', 'google_meeting_update'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            ...$this->meetingParameters(),
            'start' => ['type' => 'string', 'description' => 'New date and time, "YYYY-MM-DD HH:MM" (24-hour), in the future.'],
            'duration' => ['type' => 'number', 'description' => 'New length in minutes, 15 to 480.'],
            'title' => ['type' => 'string', 'description' => 'New title.'],
        ];
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $meeting = $this->findMeeting($user, $this->resolver, $args, 'update');

        $changes = [];
        if (!empty($args['start'])) {
            try {
                $start = Carbon::createFromFormat('Y-m-d H:i', trim((string) $args['start']), config('app.timezone'));
            } catch (\Throwable) {
                $start = false;
            }
            if (!$start) {
                throw new ToolInputException(__('Start must be a date and time as YYYY-MM-DD HH:MM.'));
            }
            if ($start->lte(now())) {
                throw new ToolInputException(__('The meeting must start in the future.'));
            }
            if ($start->format('Y-m-d H:i') !== $this->when($meeting)) {
                $changes['start'] = $start->format('Y-m-d H:i');
            }
        }
        if (isset($args['duration']) && $args['duration'] !== '') {
            $duration = (int) $args['duration'];
            if ($duration < 15 || $duration > 480) {
                throw new ToolInputException(__('Duration must be between 15 and 480 minutes.'));
            }
            if ($duration !== (int) $meeting->duration) {
                $changes['duration'] = $duration;
            }
        }
        if (isset($args['title']) && trim((string) $args['title']) !== '' && trim((string) $args['title']) !== $meeting->title) {
            $changes['title'] = trim((string) $args['title']);
            if (mb_strlen($changes['title']) > 255) {
                throw new ToolInputException(__('The meeting needs a title of at most 255 characters.'));
            }
        }
        if ($changes === []) {
            throw new ToolInputException(__('What should change on meeting ":title"? Ask the user for the new time, length or title.', ['title' => $meeting->title]));
        }

        return new PreparedAction(
            summary: __('Change meeting ":title"', ['title' => $meeting->title]),
            details: array_filter([
                __('Meeting') => $meeting->title,
                __('Project') => $meeting->project?->title,
                __('When') => isset($changes['start']) ? $this->when($meeting) . ' → ' . $changes['start'] . ' (' . config('app.timezone') . ')' : null,
                __('Length') => isset($changes['duration']) ? __(':from → :to minutes', ['from' => (int) $meeting->duration, 'to' => $changes['duration']]) : null,
                __('Title') => isset($changes['title']) ? "{$meeting->title} → {$changes['title']}" : null,
                __('On') => $this->provider($meeting) === 'zoom' ? 'Zoom' : 'Google Meet',
            ]),
            payload: ['provider' => $this->provider($meeting), 'meeting_id' => $meeting->id, 'changes' => $changes],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $meeting = $this->meetingById($user, $payload['provider'], (int) $payload['meeting_id']);
        $before = ['start' => $this->when($meeting), 'duration' => (int) $meeting->duration, 'title' => $meeting->title];

        $this->reschedule->handle($user, $meeting, $this->changes($payload['changes']));

        return new ToolOutcome(
            __('Meeting ":title" is now on :when.', ['title' => $meeting->title, 'when' => $this->when($meeting)]),
            $meeting,
            route($payload['provider'] === 'zoom' ? 'zoom-meetings.show' : 'google-meetings.show', $meeting->id, false),
            [
                'provider' => $payload['provider'],
                'meeting_id' => $meeting->id,
                'before' => array_intersect_key($before, $payload['changes']),
                'after' => ['start' => $this->when($meeting), 'duration' => (int) $meeting->duration, 'title' => $meeting->title],
            ],
        );
    }

    public function undo(array $undo, User $user): string
    {
        $meeting = $this->meetingById($user, $undo['provider'], (int) $undo['meeting_id']);
        $now = ['start' => $this->when($meeting), 'duration' => (int) $meeting->duration, 'title' => $meeting->title];
        if ($now != $undo['after']) {
            throw new ToolInputException(__('Meeting ":title" was changed again since, so it was not undone.', ['title' => $meeting->title]));
        }

        $this->reschedule->handle($user, $meeting, $this->changes($undo['before']));

        return __('Meeting ":title" put back to :when.', ['title' => $meeting->title, 'when' => $this->when($meeting)]);
    }

    /** Card values to RescheduleMeeting's changes. */
    private function changes(array $values): array
    {
        return array_filter([
            'title' => $values['title'] ?? null,
            'start_time' => isset($values['start']) ? Carbon::createFromFormat('Y-m-d H:i', $values['start'], config('app.timezone')) : null,
            'duration' => $values['duration'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
