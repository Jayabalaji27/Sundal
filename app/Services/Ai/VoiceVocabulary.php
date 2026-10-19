<?php

namespace App\Services\Ai;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;

/**
 * Names speech recognition tends to mishear ("Sundal" heard as "Simran"):
 * the app, the workspace, the projects the user can see and their team.
 * The chat model gets them on spoken turns to map sound-alikes back, and
 * server transcription gets them as a hint.
 */
class VoiceVocabulary
{
    private const MAX_PROJECTS = 25;
    private const MAX_PEOPLE = 25;

    /** @return string[] */
    public static function for(User $user): array
    {
        $workspace = Workspace::find($user->current_workspace_id);
        if (!$workspace) {
            return [config('app.name', 'Sundal')];
        }

        $projects = Project::visibleTo($user)->latest('updated_at')->limit(self::MAX_PROJECTS)->get(['id', 'title']);
        $people = $workspace->users()->wherePivot('status', 'active')->limit(self::MAX_PEOPLE)->pluck('name');
        // Open epics and sprints of those projects ("Checkout", "Sprint 3"), as people say them.
        $epics = Epic::whereIn('project_id', $projects->pluck('id'))->where('status', 'open')->limit(self::MAX_PROJECTS)->pluck('name');
        $sprints = Sprint::whereIn('project_id', $projects->pluck('id'))->whereIn('status', ['active', 'planning'])->limit(self::MAX_PROJECTS)->pluck('name');

        return collect(['Sundal', $workspace->name])
            ->concat($projects->pluck('title'))
            ->concat($people)
            ->concat($epics)
            ->concat($sprints)
            ->map(fn ($name) => trim(preg_replace('/\s+/', ' ', (string) $name)))
            ->filter(fn (string $name) => $name !== '' && mb_strlen($name) <= 60)
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values()
            ->all();
    }
}
