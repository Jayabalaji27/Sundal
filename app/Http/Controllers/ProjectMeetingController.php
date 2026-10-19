<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\MeetingDirectory;
use App\Traits\PresentsAgileBoard;
use Inertia\Inertia;

/**
 * A project's meetings, Zoom and Google Meet together (Zoho Sprints' project
 * Meetings tab). Who sees which meeting follows the Meetings pages (see
 * MeetingDirectory). Meetings are scheduled on those pages, with this project
 * already chosen.
 */
class ProjectMeetingController extends Controller
{
    use PresentsAgileBoard;

    public function index(Project $project, MeetingDirectory $directory)
    {
        $this->abortUnlessProjectVisible($project);
        $user = auth()->user();

        // Which providers are connected, and which can schedule a new meeting from here.
        $connected = $directory->connected();
        $canSchedule = [
            'zoom' => $user->hasWorkspacePermission('zoom_meeting_create') && $connected['zoom'],
            'google' => $user->hasWorkspacePermission('google_meeting_create') && $connected['google'],
        ];

        return Inertia::render('projects/Meetings', [
            'project' => $project->shellSummary(),
            'meetings' => $directory->forUser($user, $project->id),
            'canSchedule' => $canSchedule,
            'connected' => $connected,
            // Only the workspace owner holds the integration settings.
            'canConnect' => $user->hasWorkspacePermission('settings_zoom') || $user->hasWorkspacePermission('settings_google_meet'),
            'taskForm' => $this->taskFormData($project),
        ]);
    }
}
