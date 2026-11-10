<?php

namespace App\Services\Ai\Tools;

use App\Actions\Meetings\CancelMeeting;
use App\Models\User;
use App\Services\MeetingDirectory;

/**
 * Cancels an upcoming Zoom or Google Meet meeting, as Delete on the Meetings
 * pages does. Cannot be undone: the provider's meeting and join link are gone.
 */
class CancelMeetingTool extends AiTool
{
    use FindsMeetings;

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MeetingDirectory $meetings,
        private readonly CancelMeeting $cancel,
    ) {}

    protected function directory(): MeetingDirectory
    {
        return $this->meetings;
    }

    public function name(): string
    {
        return 'cancel_meeting';
    }

    public function description(): string
    {
        return 'Cancel an upcoming Zoom or Google Meet meeting (it is removed, with its join link). Only the person who scheduled it or the workspace owner can. Cannot be undone. Shows a confirmation card first.';
    }

    public function permissions(): array
    {
        return ['zoom_meeting_delete', 'google_meeting_delete'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return $this->meetingParameters();
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $meeting = $this->findMeeting($user, $this->resolver, $args, 'delete');
        $meeting->loadMissing('members:id,name');

        return new PreparedAction(
            summary: __('Cancel meeting ":title"', ['title' => $meeting->title]),
            details: array_filter([
                __('Meeting') => $meeting->title,
                __('Project') => $meeting->project?->title,
                __('When') => $this->when($meeting) . ' (' . config('app.timezone') . ')',
                __('On') => $this->provider($meeting) === 'zoom' ? 'Zoom' : 'Google Meet',
                __('Invited') => $meeting->members->pluck('name')->implode(', ') ?: null,
            ]),
            payload: ['provider' => $this->provider($meeting), 'meeting_id' => $meeting->id],
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        $meeting = $this->meetingById($user, $payload['provider'], (int) $payload['meeting_id']);
        $title = $meeting->title;

        $this->cancel->handle($user, $meeting);

        return new ToolOutcome(
            __('Cancelled meeting ":title".', ['title' => $title]),
            null,
            route($payload['provider'] === 'zoom' ? 'zoom-meetings.index' : 'google-meetings.index', [], false),
        );
    }
}
