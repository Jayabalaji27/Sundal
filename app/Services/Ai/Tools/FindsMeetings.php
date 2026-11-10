<?php

namespace App\Services\Ai\Tools;

use App\Models\GoogleMeeting;
use App\Models\User;
use App\Models\ZoomMeeting;
use App\Services\MeetingDirectory;

/**
 * Finds one meeting the user may change, for the reschedule and cancel
 * tools: among the meetings they see (MeetingDirectory), not cancelled and
 * not over, and as on the Meetings pages only the person who scheduled it
 * or the workspace owner changes it.
 */
trait FindsMeetings
{
    abstract protected function directory(): MeetingDirectory;

    protected function meetingParameters(): array
    {
        return [
            'meeting' => ['type' => 'string', 'required' => true, 'description' => 'Meeting title (or part of it) or id.'],
            'project' => ['type' => 'string', 'description' => 'Project title or id, to narrow the search.'],
            'date' => ['type' => 'string', 'description' => 'The day the meeting is on now, YYYY-MM-DD, when titles repeat.'],
        ];
    }

    /** @param  string  $verb  'update' or 'delete' (the provider permission needed) */
    protected function findMeeting(User $user, RecordResolver $resolver, array $args, string $verb): ZoomMeeting|GoogleMeeting
    {
        $ref = trim((string) ($args['meeting'] ?? ''));
        if ($ref === '') {
            throw new ToolInputException(__('Which meeting? Ask the user.'));
        }
        $project = !empty($args['project']) ? $resolver->project($user, (string) $args['project']) : null;
        $day = $resolver->date($args['date'] ?? null, __('Date'));

        $open = $this->directory()->forUser($user, $project?->id)->filter(function (array $m) use ($day) {
            $start = $m['start_time'] ? \Carbon\Carbon::parse($m['start_time']) : null;

            return $m['status'] !== 'cancelled' && $start
                && $start->copy()->addMinutes($m['duration'] ?: 60)->gte(now())
                && (!$day || $start->timezone(config('app.timezone'))->toDateString() === $day);
        });

        $id = preg_match('/^#?(\d+)$/', $ref, $m) ? (int) $m[1] : null;
        $matches = $id ? $open->where('id', $id) : collect();
        if ($matches->isEmpty()) {
            $matches = $open->filter(fn (array $m) => mb_strtolower($m['title']) === mb_strtolower($ref));
        }
        if ($matches->isEmpty()) {
            $matches = $open->filter(fn (array $m) => str_contains(mb_strtolower($m['title']), mb_strtolower($ref)));
        }

        if ($matches->isEmpty()) {
            throw new ToolInputException(__('No upcoming meeting matches ":ref" that this user can see. Ask the user to check the name.', ['ref' => $ref]));
        }
        if ($matches->count() > 1) {
            throw new ToolInputException(__('Several upcoming meetings match ":ref": :list. Ask the user which one (a date helps); do not guess.', [
                'ref' => $ref,
                'list' => $matches->take(5)->map(fn (array $m) => "{$m['title']} (" . \Carbon\Carbon::parse($m['start_time'])->timezone(config('app.timezone'))->format('Y-m-d H:i') . ')')->implode('; '),
            ]));
        }

        $found = $matches->first();
        if (!$user->hasWorkspacePermission(($found['provider'] === 'zoom' ? 'zoom_meeting_' : 'google_meeting_') . $verb)) {
            throw new ToolInputException(__('The user is not allowed to change :provider meetings.', ['provider' => $found['provider'] === 'zoom' ? 'Zoom' : 'Google Meet']));
        }

        return $this->meetingById($user, $found['provider'], (int) $found['id']);
    }

    /** Re-load a meeting when a confirmed card runs, with the same rule for who may change it. */
    protected function meetingById(User $user, string $provider, int $id): ZoomMeeting|GoogleMeeting
    {
        $meeting = ($provider === 'zoom' ? ZoomMeeting::query() : GoogleMeeting::query())
            ->where('workspace_id', $user->current_workspace_id)
            ->whereKey($id)
            ->with('project:id,title')
            ->first() ?? throw new ToolInputException(__('The meeting on this card no longer exists.'));

        $owner = $user->currentWorkspace?->getMemberRole($user) === 'owner';
        if (!$owner && (int) $meeting->user_id !== (int) $user->id) {
            throw new ToolInputException(__('Only the person who scheduled this meeting, or the workspace owner, can change it.'));
        }

        return $meeting;
    }

    protected function provider(ZoomMeeting|GoogleMeeting $meeting): string
    {
        return $meeting instanceof ZoomMeeting ? 'zoom' : 'google';
    }

    protected function when(ZoomMeeting|GoogleMeeting $meeting): string
    {
        return \Carbon\Carbon::parse($meeting->start_time)->timezone(config('app.timezone'))->format('Y-m-d H:i');
    }
}
