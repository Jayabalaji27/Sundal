<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\ZoomMeeting;
use App\Models\GoogleMeeting;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class CalendarController extends Controller
{
    use HasPermissionChecks;

    public function index(Request $request)
    {
        $this->authorizePermission('task_calendar_view');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }

        $events = collect();
        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Get tasks
        try {
            $tasksQuery = Task::with(['project', 'assignedTo', 'taskStage'])
                ->whereHas('project', function ($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                })
                ->whereNotNull('end_date');

            // Access control based on workspace role
            if ($userWorkspaceRole !== 'owner') {
                $tasksQuery->whereHas('project', function ($projectQuery) use ($user) {
                    $projectQuery->where(function ($q) use ($user) {
                        $q->whereHas('members', function ($memberQuery) use ($user) {
                            $memberQuery->where('user_id', $user->id);
                        })
                        ->orWhereHas('clients', function ($clientQuery) use ($user) {
                            $clientQuery->where('user_id', $user->id);
                        })
                        ->orWhere('created_by', $user->id);
                    });
                });

                // Filter tasks by assignment for members only (not clients)
                if ($userWorkspaceRole === 'member') {
                    $tasksQuery->where(function($taskQuery) use ($user) {
                        $taskQuery->where('assigned_to', $user->id)
                            ->orWhere('created_by', $user->id);
                    });
                }
            }

            $tasks = $tasksQuery->get()->map(function ($task) {
                return [
                    'id' => 'task-' . $task->id,
                    'title' => $task->title,
                    'start' => $task->start_date ?: $task->end_date,
                    'end' => $task->end_date,
                    'type' => 'task',
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'task_id' => $task->id,
                    'description' => $task->description,
                    'stage' => $task->taskStage?->name ?? 'To Do',
                    'priority' => $task->priority,
                    'start_date' => $task->start_date,
                    'due_date' => $task->end_date,
                    'progress' => $task->progress ?? 0,
                    'parent_name' => $task->project?->title,
                    'project_name' => $task->project?->title,
                    'is_googlecalendar_sync' => $task->is_googlecalendar_sync ?? false
                ];
            });
            $events = $events->merge($tasks);
        } catch (\Exception $e) {
            // Skip if Task model doesn't exist
        }

        // Get zoom meetings
        try {
            $meetingsQuery = ZoomMeeting::with(['project', 'user'])
                ->forWorkspace($workspace->id);

            // Access control based on workspace role
            if ($userWorkspaceRole !== 'owner') {
                $meetingsQuery->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                });
            }

            $meetings = $meetingsQuery->get()->map(function ($meeting) {
                return [
                    'id' => 'meeting-' . $meeting->id,
                    'title' => $meeting->title,
                    'start' => $meeting->start_time,
                    'end' => $meeting->end_time,
                    'type' => 'meeting',
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'meeting_id' => $meeting->id,
                    'description' => $meeting->description,
                    'status' => $meeting->status,
                    'start_time' => $meeting->start_time,
                    'duration' => $meeting->duration,
                    'parent_name' => $meeting->project?->title,
                    'is_googlecalendar_sync' => $meeting->is_googlecalendar_sync ?? false
                ];
            });
            $events = $events->merge($meetings);
        } catch (\Exception $e) {
            // Skip if ZoomMeeting model doesn't exist
        }

        // Get google meetings
        try {
            $googleMeetingsQuery = GoogleMeeting::with(['project', 'user'])
                ->forWorkspace($workspace->id);

            // Access control based on workspace role
            if ($userWorkspaceRole !== 'owner') {
                $googleMeetingsQuery->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                });
            }

            $googleMeetings = $googleMeetingsQuery->get()->map(function ($meeting) {
                return [
                    'id' => 'google-meeting-' . $meeting->id,
                    'title' => $meeting->title,
                    'start' => $meeting->start_time,
                    'end' => $meeting->end_time,
                    'type' => 'google_meeting',
                    'backgroundColor' => '#10B77F',
                    'borderColor' => '#059652ff',
                    'meeting_id' => $meeting->id,
                    'description' => $meeting->description,
                    'status' => $meeting->status,
                    'start_time' => $meeting->start_time,
                    'duration' => $meeting->duration,
                    'parent_name' => $meeting->project?->title,
                    'is_googlecalendar_sync' => $meeting->is_googlecalendar_sync ?? false
                ];
            });
            $events = $events->merge($googleMeetings);
        } catch (\Exception $e) {
            // Skip if GoogleMeeting model doesn't exist
        }

        // Get Google Calendar sync settings from company owner
        $companyOwner = $workspace->owner; // Get the company owner
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $companyOwner->id, $workspace->id) === '1';
        
        return Inertia::render('calendar/index', [
            'events' => $events->values()->toArray(),
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function getTask(Task $task)
    {
        // Clients don't have task_view (no standalone Tasks page access) but can still
        // see task details surfaced inside the calendar via task_calendar_view_tasks.
        if (!$this->checkAnyPermission(['task_view', 'task_calendar_view_tasks'])) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }

        $task->load([
            'project',
            'taskStage',
            'assignedTo',
            'creator'
        ]);

        return response()->json([
            'task' => $task
        ]);
    }
}