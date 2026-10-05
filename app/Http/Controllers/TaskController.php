<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\TaskStage;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use HasPermissionChecks;
    
    protected $googleCalendarService;

    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }
    public function index(Request $request): Response
    {
        $this->authorizePermission('task_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }

        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Members only see their own tasks, except when viewing a specific project
        $query = Task::with(['project', 'taskStage', 'assignedTo', 'creator', 'milestone'])
            ->visibleTo($user, !$request->project_id);

        if ($request->project_id) {
            $query->forProject($request->project_id);
        }

        if ($request->stage_id) {
            $query->byStage($request->stage_id);
        }

        if ($request->priority) {
            $query->byPriority($request->priority);
        }

        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->search) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Add sorting functionality
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Define allowed sort fields to prevent SQL injection
        $allowedSortFields = [
            'title',
            'priority', 
            'end_date',
            'start_date',
            'created_at',
            'updated_at',
            'progress'
        ];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest(); // Default sorting
        }

        // Default to kanban view and get all data without pagination
        $view = $request->get('view', 'kanban');

        if ($view === 'kanban') {
            $tasks = $query->get();
        } else {
            // Same choices as the list view's "Per Page" select - a value it doesn't
            // offer (the old default 10) left that select blank.
            $perPage = (int) $request->get('per_page', 20);
            $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
            $tasks = $query->paginate($perPage);
        }

        // Process avatars for tasks
        $taskCollection = ($tasks instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $tasks->getCollection() : $tasks;
        $taskCollection->each(function ($task) {
            foreach (['assignedTo', 'creator'] as $relation) {
                if ($task->$relation) {
                    $task->$relation->avatar = check_file($task->$relation->avatar)
                        ? get_file($task->$relation->avatar)
                        : get_file('avatars/avatar.png');
                }
            }
        });

        // Apply same access control to projects dropdown as used for task filtering
        $projectsQuery = Project::forWorkspace($user->current_workspace_id)
            ->with(['milestones', 'members.user']);

        // If not workspace owner, only show accessible projects
        if ($userWorkspaceRole !== 'owner') {
            $projectsQuery->where(function ($q) use ($user) {
                $q->whereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })
                    ->orWhereHas('clients', function ($clientQuery) use ($user) {
                        $clientQuery->where('user_id', $user->id);
                    })
                    ->orWhere('created_by', $user->id);
            });
        }

        $projects = $projectsQuery->get();
        $stages = TaskStage::forWorkspace($user->current_workspace_id)->ordered()->get();
        $members = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        // Get Google Calendar sync settings from company owner
        $companyOwner = $workspace->owner; // Get the company owner
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $companyOwner->id, $workspace->id) === '1';

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'stages' => $stages,
            'members' => $members,
            'filters' => array_merge(
                $request->only(['project_id', 'stage_id', 'priority', 'assigned_to', 'search', 'per_page', 'sort_field', 'sort_direction']),
                ['view' => $view]
            ),
            'project_name' => $request->project_name,
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => [
                'create' => $this->checkPermission('task_create'),
                'update' => $this->checkPermission('task_update'),
                'delete' => $this->checkPermission('task_delete'),
                'duplicate' => $this->checkPermission('task_duplicate'),
                'change_status' => $this->checkPermission('task_change_status'),
                'assign_users' => $this->checkPermission('task_assign_users'),
                'manage_stages' => $this->checkPermission('task_manage_stages'),
                'add_comments' => $this->checkPermission('task_add_comments'),
                'add_attachments' => $this->checkPermission('task_add_attachments'),
                'manage_checklists' => $this->checkPermission('task_manage_checklists'),
            ],
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function show(Task $task)
    {
        $this->authorizePermission('task_view');

        $user = auth()->user();
        abort_if(
            ! \App\Models\Project::visibleTo($user)->whereKey($task->project_id)->exists(),
            404
        );

        $task->load([
            'project.workspace',
            'project.members.user',
            'taskStage',
            'assignedTo',
            'creator',
            'milestone',
            'comments.user',
            'checklists.assignedTo',
            'checklists.creator',
            'attachments.mediaItem'
        ]);

        // Ensure MediaItem appended attributes are loaded
        $task->attachments->load('mediaItem');
        $task->attachments->each(function ($attachment) {
            if ($attachment->mediaItem) {
                // Force load the media to ensure appended attributes work
                $attachment->mediaItem->getFirstMedia('images');
            }
        });

        $currentUser = auth()->user();
        $workspace = $currentUser->currentWorkspace;

        // Ensure task belongs to current workspace
        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }

        // Add permission flags to comments
        $task->comments->each(function ($comment) use ($currentUser) {
            $comment->can_update = $comment->canBeUpdatedBy($currentUser);
            $comment->can_delete = $comment->canBeDeletedBy($currentUser);
        });

        // Add permission flags to checklists
        $task->checklists->each(function ($checklist) use ($currentUser) {
            $checklist->can_update = $checklist->canBeUpdatedBy($currentUser);
            $checklist->can_delete = $checklist->canBeDeletedBy($currentUser);
        });

        $allMembers = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        // Get project members only (no clients)
        $projectMembers = $task->project->members->filter(function ($member) {
            return $member->user && $member->user->type !== 'client';
        })->pluck('user');

        // Process avatars for task actors
        foreach (['assignedTo', 'creator'] as $relation) {
            if ($task->$relation) {
                $task->$relation->avatar = check_file($task->$relation->avatar)
                    ? get_file($task->$relation->avatar)
                    : get_file('avatars/avatar.png');
            }
        }

        // Process avatars for commenters
        $task->comments->each(function ($comment) {
            if ($comment->user) {
                $comment->user->avatar = check_file($comment->user->avatar)
                    ? get_file($comment->user->avatar)
                    : get_file('avatars/avatar.png');
            }
        });

        // Process avatars for checklist actors
        $task->checklists->each(function ($checklist) {
            foreach (['assignedTo', 'creator'] as $relation) {
                if ($checklist->$relation) {
                    $checklist->$relation->avatar = check_file($checklist->$relation->avatar)
                        ? get_file($checklist->$relation->avatar)
                        : get_file('avatars/avatar.png');
                }
            }
        });

        // Process avatars for members list
        $allMembers = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get()->each(function ($user) {
            $user->avatar = check_file($user->avatar)
                ? get_file($user->avatar)
                : get_file('avatars/avatar.png');
        });

        $projectMembers = $projectMembers->each(function ($user) {
            $user->avatar = check_file($user->avatar)
                ? get_file($user->avatar)
                : get_file('avatars/avatar.png');
        });

        $stages = TaskStage::forWorkspace($currentUser->current_workspace_id)->ordered()->get();
        $milestones = $task->project->milestones ?? [];

        // Get workspace role for permission check
        $workspace = $currentUser->currentWorkspace;
        $workspaceRole = $workspace ? $workspace->getMemberRole($currentUser) : null;

        return response()->json([
            'task' => $task,
            'members' => $projectMembers->isNotEmpty() ? $projectMembers : $allMembers,
            'stages' => $stages,
            'milestones' => $milestones,
            'workspace_role' => $workspaceRole,
            'permissions' => [
                'update' => $this->checkPermission('task_update') && $this->canModifyTask($task, $currentUser),
                'delete' => $this->checkPermission('task_delete') && $this->canModifyTask($task, $currentUser),
                'duplicate' => $this->checkPermission('task_duplicate'),
                'change_status' => $this->checkPermission('task_change_status') && $this->canModifyTask($task, $currentUser),
                'assign_users' => $this->checkPermission('task_assign_users') && $this->canModifyTask($task, $currentUser),
                'add_comments' => $this->checkPermission('task_add_comments'),
                'add_attachments' => $this->checkPermission('task_add_attachments'),
                'manage_checklists' => $this->checkPermission('task_manage_checklists'),
            ]
        ]);
    }



    public function store(Request $request)
    {
        $this->authorizePermission('task_create');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(403, 'No workspace selected.');
        }
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'assigned_to' => 'nullable|exists:users,id',
            'is_googlecalendar_sync' => 'nullable|boolean'
        ]);

        // Ensure project belongs to current workspace
        $project = Project::find($validated['project_id']);
        if (!$project || $project->workspace_id != $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }

        // Get first stage for the workspace
        $firstStage = TaskStage::forWorkspace(auth()->user()->current_workspace_id)
            ->ordered()
            ->first();

        // assigned_to is optional: a task created without it is unassigned.
        $assignedTo = $validated['assigned_to'] ?? null;

        // One unit: if a follow-up step fails, no half-created task is left behind.
        DB::transaction(function () use ($validated, $firstStage, $assignedTo) {
            $task = Task::create([
                ...$validated,
                'assigned_to' => $assignedTo,
                'task_stage_id' => $firstStage->id,
                'created_by' => auth()->id(),
                'progress' => 0
            ]);

            // Sync with Google Calendar if enabled
            if ($validated['is_googlecalendar_sync'] ?? false) {
                $this->syncTaskWithGoogleCalendar($task);
            }

            // Fire event for Slack notification
            if (!config('app.is_demo', true)) {
                event(new \App\Events\TaskCreated($task));
            }

            // Fire event for email notification if task is assigned
            if ($assignedTo) {
                $assignedUser = User::find($assignedTo);
                if ($assignedUser) {
                    $task->load('project');
                    if (!config('app.is_demo', true)) {
                        event(new \App\Events\TaskAssigned($task, $assignedUser, auth()->user()));
                    }
                }
            }
        });

        return back()->with('success', __('Task created successfully!'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizePermission('task_update');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        abort_unless($this->canModifyTask($task, $user), 403, __('You can only edit tasks you created or are assigned to.'));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'priority' => 'required|in:low,medium,high,critical',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'assigned_to' => 'nullable|exists:users,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'is_googlecalendar_sync' => 'boolean'
        ]);

        // Check if assigned_to changed
        $oldAssignedTo = $task->assigned_to;
        $newAssignedTo = $validated['assigned_to'] ?? null;

        // Reassigning a task needs task_assign_users (owners/managers).
        if (array_key_exists('assigned_to', $validated)
            && (int) $oldAssignedTo !== (int) $newAssignedTo
            && !$this->checkPermission('task_assign_users')) {
            abort(403, __('You do not have permission to reassign tasks.'));
        }

        $task->update($validated);

        // Sync with Google Calendar if enabled
        if ($validated['is_googlecalendar_sync'] ?? false) {
            $this->syncTaskWithGoogleCalendar($task);
        } elseif ($task->google_calendar_event_id && !($validated['is_googlecalendar_sync'] ?? false)) {
            // Remove from Google Calendar if sync was disabled
            $this->googleCalendarService->deleteEvent($task->google_calendar_event_id, auth()->id());
            $task->update(['google_calendar_event_id' => null]);
        }

        // Fire event for email notification if task assignment changed
        if ($newAssignedTo && $oldAssignedTo !== $newAssignedTo) {
            $assignedUser = User::find($newAssignedTo);
            if ($assignedUser) {
                $task->load('project');
                if (!config('app.is_demo', true)) {
                    event(new \App\Events\TaskAssigned($task, $assignedUser, auth()->user()));
                }
            }
        }

        return back()->with('success', __('Task updated successfully!'));
    }

    public function destroy(Task $task)
    {
        $this->authorizePermission('task_delete');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        abort_unless($this->canModifyTask($task, $user), 403, __('You can only delete tasks you created or are assigned to.'));

        // Delete Google Calendar event
        if ($task->google_calendar_event_id) {
            try {
                $this->googleCalendarService->deleteEvent($task->google_calendar_event_id, auth()->id(), $user->current_workspace_id);
            } catch (\Exception $e) {
                \Log::error('Failed to delete Google Calendar event', [
                    'task_id' => $task->id,
                    'event_id' => $task->google_calendar_event_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $task->delete();

        return back()->with('success', __('Task deleted successfully!'));
    }

    public function duplicate(Task $task)
    {
        $this->authorizePermission('task_duplicate');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        $newTask = $task->replicate();
        $newTask->title = $task->title . ' (Copy)';
        $newTask->start_date = null;
        $newTask->end_date = null;
        $newTask->progress = 0;
        $newTask->created_by = auth()->id();
        $newTask->save();

        // Copy checklists
        foreach ($task->checklists as $checklist) {
            $newChecklist = $checklist->replicate();
            $newChecklist->task_id = $newTask->id;
            $newChecklist->is_completed = false;
            $newChecklist->created_by = auth()->id();
            $newChecklist->save();
        }

        return back()->with('success', __('Task duplicated successfully!'));
    }

    public function changeStage(Request $request, Task $task)
    {
        $this->authorizePermission('task_change_status');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        // Back with a message rather than a 403 page: this is usually a kanban drag.
        if (!$this->canModifyTask($task, $user)) {
            return back()->with('error', __('You can only move tasks you created or are assigned to.'));
        }
        $validated = $request->validate([
            'task_stage_id' => 'required|exists:task_stages,id'
        ]);

        $oldStage = $task->taskStage->name ?? 'Unknown';
        $task->update($validated);
        $newStage = TaskStage::find($validated['task_stage_id'])->name ?? 'Unknown';

        // Fire event for Slack notification
        if (!config('app.is_demo', true)) {
            event(new \App\Events\TaskStageUpdated($task, $oldStage, $newStage));
        }

        return back()->with('success', __('Task stage updated successfully!'));
    }

    /**
     * Sync task with Google Calendar
     */
    private function syncTaskWithGoogleCalendar(Task $task)
    {
        try {
            $user = auth()->user();
            $workspaceId = $user->current_workspace_id;
            
            // Check if Google Calendar is enabled and configured from company owner
            $companyOwner = $user->currentWorkspace->owner;
            $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $companyOwner->id, $workspaceId);
            
            if ($googleCalendarEnabled !== '1') {
                return;
            }
            
            if ($task->google_calendar_event_id) {
                // Update existing event
                $this->googleCalendarService->updateEvent($task->google_calendar_event_id, $task, $user->id, $workspaceId);
            } else {
                // Create new event
                $eventId = $this->googleCalendarService->createEvent($task, $user->id, $workspaceId);
                if ($eventId) {
                    $task->update(['google_calendar_event_id' => $eventId]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to sync task with Google Calendar', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get tasks for calendar view (including Google Calendar tasks)
     */
    public function getCalendarTasks(Request $request)
    {
        $user = auth()->user();
        $calendarView = $request->get('calendar_view', 'local'); // 'local' or 'google'
        
        // Same visibility as the calendar page, and only the assignee fields the
        // calendar shows - full user rows carry plan, 2FA and timer data.
        $tasks = Task::with(['project:id,title', 'taskStage', 'assignedTo:id,name,avatar'])
            ->visibleOnCalendarTo($user)
            ->when($calendarView === 'google', function ($query) {
                $query->where('is_googlecalendar_sync', true);
            })
            ->get();
            
        return response()->json([
            'tasks' => $tasks,
            'calendar_view' => $calendarView
        ]);
    }

    /**
     * Owners and managers can change any task in the workspace. Members may only
     * change tasks they created, are assigned to, or are listed as a member of.
     * Clients (who only hold task_change_status) may only move tasks on their own
     * projects whose shared settings expose tasks to them.
     */
    private function canModifyTask(Task $task, User $user): bool
    {
        $workspace = $user->currentWorkspace;
        if (!$workspace || $workspace->isOwner($user)) {
            return true;
        }

        $role = $workspace->getMemberRole($user);

        if ($role === 'client') {
            return !empty($task->project?->shared_settings['task'])
                && $task->project->clients()->where('user_id', $user->id)->exists();
        }

        if ($role !== 'member') {
            return true;
        }

        return (int) $task->created_by === (int) $user->id
            || (int) $task->assigned_to === (int) $user->id
            || $task->members()->where('users.id', $user->id)->exists();
    }
}
