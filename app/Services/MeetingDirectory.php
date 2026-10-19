<?php

namespace App\Services;

use App\Models\GoogleMeeting;
use App\Models\User;
use App\Models\ZoomMeeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Zoom and Google Meet meetings in one list, as the project Meetings tab and
 * the AI assistant show them. Who sees which meeting follows the Meetings
 * pages: the workspace owner sees all, everyone else the ones they set up or
 * are invited to.
 */
class MeetingDirectory
{
    public const PROVIDERS = ['zoom', 'google'];

    /** @return array{zoom: bool, google: bool} which providers the workspace owner has connected */
    public function connected(): array
    {
        $check = fn (string $service) => rescue(fn () => app($service)->hasValidCredentials(), false, false);

        return ['zoom' => (bool) $check(ZoomService::class), 'google' => (bool) $check(GoogleMeetService::class)];
    }

    /**
     * The meetings this person may see, soonest first.
     *
     * @param  int|null  $projectId  only this project's meetings
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user, ?int $projectId = null): Collection
    {
        $isOwner = $user->currentWorkspace?->getMemberRole($user) === 'owner';

        $scope = function (Builder $query) use ($user, $isOwner, $projectId) {
            $query->where('workspace_id', $user->current_workspace_id)
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->with(['members:id,name,avatar', 'project:id,title']);
            if (!$isOwner) {
                $query->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id))->orWhere('user_id', $user->id));
            }

            return $query;
        };

        $meetings = collect();
        if ($user->hasWorkspacePermission('zoom_meeting_view_any')) {
            $meetings = $meetings->concat($scope(ZoomMeeting::query())->get()->map(fn ($m) => $this->present($m, 'zoom')));
        }
        if ($user->hasWorkspacePermission('google_meeting_view_any')) {
            $meetings = $meetings->concat($scope(GoogleMeeting::query())->get()->map(fn ($m) => $this->present($m, 'google')));
        }

        return $meetings->sortBy('start_time')->values();
    }

    private function present(Model $meeting, string $provider): array
    {
        return [
            'key' => "{$provider}-{$meeting->id}",
            'id' => $meeting->id,
            'provider' => $provider,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'project' => $meeting->project?->title,
            'start_time' => $meeting->start_time?->toIso8601String(),
            'duration' => (int) $meeting->duration,
            'status' => $meeting->status,
            'join_url' => $meeting->join_url ?: ($meeting->meet_url ?? null),
            'show_url' => route($provider === 'zoom' ? 'zoom-meetings.show' : 'google-meetings.show', $meeting->id),
            'attendees' => $meeting->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'avatar' => check_file($member->avatar) ? get_file($member->avatar) : null,
            ])->values(),
        ];
    }
}
