<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\MeetingDirectory;

/** Zoom and Google Meet meetings the user may see (as on the Meetings pages), with join links. */
class ListMeetings extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MeetingDirectory $directory,
    ) {}

    public function name(): string
    {
        return 'list_meetings';
    }

    public function description(): string
    {
        return 'List Zoom and Google Meet meetings the user is in (the workspace owner sees all): upcoming by default, or past. Filter by project. Upcoming meetings come with their join link.';
    }

    public function permissions(): array
    {
        return ['zoom_meeting_view_any', 'google_meeting_view_any'];
    }

    public function parameters(): array
    {
        return [
            'project' => ['type' => 'string', 'description' => 'Project title or id.'],
            'when' => ['type' => 'enum', 'options' => ['upcoming', 'past', 'all'], 'description' => 'Defaults to upcoming.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $project = !empty($args['project']) ? $this->resolver->project($user, (string) $args['project']) : null;
        $when = in_array($args['when'] ?? null, ['upcoming', 'past', 'all'], true) ? $args['when'] : 'upcoming';
        $now = now();

        $meetings = $this->directory->forUser($user, $project?->id)->filter(function (array $m) use ($when, $now) {
            $ends = $m['start_time'] ? \Carbon\Carbon::parse($m['start_time'])->addMinutes($m['duration'] ?: 60) : null;
            $upcoming = $m['status'] !== 'cancelled' && $ends && $ends->gte($now);

            return $when === 'all' || ($when === 'upcoming' ? $upcoming : !$upcoming);
        });
        if ($when === 'past') {
            $meetings = $meetings->reverse();
        }
        $connected = $this->directory->connected();

        return [
            'meetings' => $meetings->take(20)->map(fn (array $m) => array_filter([
                'title' => $m['title'],
                'provider' => $m['provider'] === 'zoom' ? 'Zoom' : 'Google Meet',
                'project' => $m['project'],
                'starts' => $m['start_time'] ? \Carbon\Carbon::parse($m['start_time'])->timezone(config('app.timezone'))->format('Y-m-d H:i') : null,
                'minutes' => $m['duration'],
                'status' => $m['status'],
                'attendees' => collect($m['attendees'])->pluck('name')->all(),
                'join_url' => $when !== 'past' ? $m['join_url'] : null,
                'link' => parse_url($m['show_url'], PHP_URL_PATH),
            ], fn ($v) => $v !== null && $v !== []))->values()->all(),
            'shown' => min(20, $meetings->count()),
            'total_matching' => $meetings->count(),
            // New meetings need Zoom or Google Meet connected (Settings › Integrations, by the owner).
            'connected' => array_keys(array_filter($connected)),
        ];
    }
}
