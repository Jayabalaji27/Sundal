<?php

namespace App\Services\Ai\Tools;

use App\Actions\Meetings\ScheduleMeeting;
use App\Models\User;
use App\Services\MeetingDirectory;
use Carbon\Carbon;

/**
 * Schedules a Zoom or Google Meet meeting for a project, through the same
 * action as the Meetings pages. Like those pages, it needs the provider
 * connected (Settings › Integrations), since that is what gives the join link.
 */
class ScheduleMeetingTool extends AiTool
{
    private const PROVIDERS = ['zoom' => 'Zoom', 'google' => 'Google Meet'];

    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly MeetingDirectory $directory,
        private readonly ScheduleMeeting $schedule,
    ) {}

    public function name(): string
    {
        return 'schedule_meeting';
    }

    public function description(): string
    {
        return 'Schedule a Zoom or Google Meet meeting for a project (stand-up, sprint review, client call) and invite people; they get an email with the join link. Shows a confirmation card first. Needs Zoom or Google Meet connected by the workspace owner.';
    }

    public function permissions(): array
    {
        return ['zoom_meeting_create', 'google_meeting_create'];
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function parameters(): array
    {
        return [
            'title' => ['type' => 'string', 'required' => true, 'description' => 'Meeting title, e.g. "Sprint 3 review".'],
            'project' => ['type' => 'string', 'required' => true, 'description' => 'Project title or id.'],
            'start' => ['type' => 'string', 'required' => true, 'description' => 'Date and time, "YYYY-MM-DD HH:MM" (24-hour), in the future.'],
            'duration' => ['type' => 'number', 'description' => 'Minutes, 15 to 480. Defaults to 30.'],
            'provider' => ['type' => 'enum', 'options' => array_keys(self::PROVIDERS), 'description' => 'zoom or google. Leave out to use the one that is connected.'],
            'attendees' => ['type' => 'string', 'description' => 'People to invite: "me", names or emails separated by ";". Defaults to the project team.'],
            'description' => ['type' => 'string', 'description' => 'Agenda.'],
        ];
    }

    /** The providers this person can schedule with right now. */
    private function usable(User $user): array
    {
        $connected = $this->directory->connected();

        return array_values(array_filter(array_keys(self::PROVIDERS), fn ($p) => $connected[$p] && $user->hasWorkspacePermission("{$p}_meeting_create")));
    }

    public function prepare(array $args, User $user): PreparedAction
    {
        $usable = $this->usable($user);
        if (!$usable) {
            throw new ToolInputException(__('Zoom or Google Meet is not connected yet, so meetings cannot be scheduled. The workspace owner connects it in Settings › Integrations.'));
        }
        $provider = $args['provider'] ?? $usable[0];
        if (!in_array($provider, $usable, true)) {
            throw new ToolInputException(__(':name cannot be used: it is not connected, or this user may not schedule with it. Use :other.', [
                'name' => self::PROVIDERS[$provider] ?? (string) $provider,
                'other' => implode(' or ', array_map(fn ($p) => self::PROVIDERS[$p], $usable)),
            ]));
        }

        $project = $this->resolver->project($user, (string) ($args['project'] ?? ''));
        $title = trim((string) ($args['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ToolInputException(__('The meeting needs a title of at most 255 characters.'));
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', trim((string) ($args['start'] ?? '')), config('app.timezone'));
        } catch (\Throwable) {
            $start = false;
        }
        if (!$start) {
            throw new ToolInputException(__('Start must be a date and time as YYYY-MM-DD HH:MM.'));
        }
        if ($start->lte(now())) {
            throw new ToolInputException(__('The meeting must start in the future.'));
        }
        $duration = (int) ($args['duration'] ?? 30);
        if ($duration < 15 || $duration > 480) {
            throw new ToolInputException(__('Duration must be between 15 and 480 minutes.'));
        }

        // Invitees: the people named, or the project team (clients only when named).
        $refs = array_values(array_filter(array_map('trim', explode(';', (string) ($args['attendees'] ?? '')))));
        $people = $refs
            ? collect($refs)->map(fn (string $ref) => $this->resolver->member($user, $ref))->unique('id')
            : $project->members()->with('user:id,name,type')->get()->pluck('user')->filter()
                ->reject(fn ($u) => $u->type === 'client')->unique('id');
        if ($people->isEmpty()) {
            throw new ToolInputException(__('Who should be invited? Project :project has no team yet.', ['project' => $project->title]));
        }

        return new PreparedAction(
            summary: __('Schedule ":title" on :provider', ['title' => $title, 'provider' => self::PROVIDERS[$provider]]),
            details: array_filter([
                __('Project') => $project->title,
                __('When') => $start->format('D, d M Y · H:i') . ' (' . $start->getTimezone()->getName() . ')',
                __('Length') => __(':count minutes', ['count' => $duration]),
                __('On') => self::PROVIDERS[$provider],
            ]),
            payload: [
                'provider' => $provider,
                'title' => $title,
                'description' => isset($args['description']) ? mb_substr(trim((string) $args['description']), 0, 5000) : null,
                'project_id' => $project->id,
                'start' => $start->format('Y-m-d H:i'),
                'duration' => $duration,
                'member_ids' => $people->pluck('id')->values()->all(),
            ],
            items: $people->pluck('name')->values()->all(),
        );
    }

    public function execute(array $payload, User $user): ToolOutcome
    {
        if (!in_array($payload['provider'], $this->usable($user), true)) {
            throw new ToolInputException(__(':name is no longer connected, so nothing was scheduled.', ['name' => self::PROVIDERS[$payload['provider']]]));
        }
        $project = $this->resolver->byId($this->resolver->projects($user), (int) $payload['project_id'], __('project'));
        $members = $this->resolver->members($user)->whereIn('id', $payload['member_ids'])->pluck('id')->all();

        $meeting = $this->schedule->handle($user, $payload['provider'], [
            'title' => $payload['title'],
            'description' => $payload['description'],
            'start_time' => Carbon::createFromFormat('Y-m-d H:i', $payload['start'], config('app.timezone')),
            'timezone' => config('app.timezone'),
            'duration' => (int) $payload['duration'],
            'project_id' => $project->id,
            'member_ids' => $members,
        ]);

        $name = self::PROVIDERS[$payload['provider']];

        return new ToolOutcome(
            $meeting->join_url
                ? __('Scheduled ":title" on :provider. Join link: :url', ['title' => $meeting->title, 'provider' => $name, 'url' => $meeting->join_url])
                : __('Scheduled ":title", but :provider did not return a join link; check the connection in Settings › Integrations.', ['title' => $meeting->title, 'provider' => $name]),
            $meeting,
            route($payload['provider'] === 'zoom' ? 'zoom-meetings.show' : 'google-meetings.show', $meeting->id, false),
        );
    }
}
