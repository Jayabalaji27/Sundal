<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\TaskStage;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Traits\HasPermissionChecks;
use App\Traits\PresentsAgileBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use HasPermissionChecks, PresentsAgileBoard;

    /** Views of the Tasks page; the cookie keeps the last one picked. */
    private const VIEWS = ['detail', 'plain', 'sheet'];

    public const VIEW_COOKIE = 'sundal_tasks_view';

    /** The most cards the Detail and Plain views show at once. */
    private const BOARD_LIMIT = 300;
    
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

        // Tasks and bugs are one list; ?type=bug shows only bugs.
        $query->ofType($request->type);
        if (in_array($request->severity, Task::SEVERITIES, true)) {
            $query->where('tasks.severity', $request->severity);
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

        // The same three views as a project's Board: Detail and Plain cards by stage
        // (every project at once, at most BOARD_LIMIT items), or the Sheet, this
        // paginated table.
        $view = $this->tasksView($request);
        $tasks = $board = null;

        if ($view !== 'sheet') {
            $total = (clone $query)->count();
            $board = [
                'cards' => $query->with('epic:id,name,color')->limit(self::BOARD_LIMIT)->get()
                    ->map(fn (Task $task) => $this->taskCard($task) + ['project' => $task->project?->only('id', 'title')])
                    ->values(),
                'total' => $total,
                'limit' => self::BOARD_LIMIT,
            ];
        } else {
            // Same choices as the list view's "Per Page" select - a value it doesn't
            // offer (the old default 10) left that select blank.
            $perPage = (int) $request->get('per_page', 20);
            $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
            $tasks = $query->paginate($perPage);

            $tasks->getCollection()->each(function ($task) {
                foreach (['assignedTo', 'creator'] as $relation) {
                    if ($task->$relation) {
                        $task->$relation->avatar = check_file($task->$relation->avatar)
                            ? get_file($task->$relation->avatar)
                            : get_file('avatars/avatar.png');
                    }
                }
            });
        }

        // Apply same access control to projects dropdown as used for task filtering
        $projectsQuery = Project::forWorkspace($user->current_workspace_id)
            ->with(['milestones', 'members.user', 'epics' => fn ($q) => $q->where('status', 'open')->orderBy('name')->select('id', 'project_id', 'name', 'color')]);

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
            'board' => $board,
            'projects' => $projects,
            'stages' => $stages,
            'members' => $members,
            'filters' => array_merge(
                $request->only(['project_id', 'stage_id', 'priority', 'assigned_to', 'type', 'severity', 'search', 'per_page', 'sort_field', 'sort_direction']),
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

    /** ?view= wins (links from the AI assistant and the dashboard); then the last view picked. */
    private function tasksView(Request $request): string
    {
        $view = $request->query('view') ?? $request->cookie(self::VIEW_COOKIE);
        // Older links: "kanban" was the board, "list" the table.
        $view = ['kanban' => 'detail', 'list' => 'sheet'][$view] ?? $view;

        return in_array($view, self::VIEWS, true) ? $view : 'sheet';
    }

    public function show(Task $task)
    {
        $this->authorizePermission('task_view');

        $user = auth()->user();
        abort_if(
            ! \App\Models\Project::visibleTo($user)->whereKey($task->project_id)->exists(),
            404
        );

        // This answers the task modal's fetch with JSON. A page visit (a link from
        // the AI assistant, search, an email or an old bug URL) opens the task's
        // modal on the Tasks list instead of showing raw data.
        if (request()->header('X-Inertia') || str_contains((string) request()->header('Accept'), 'text/html')) {
            return redirect()->route('tasks.index', ['task_id' => $task->id, ...($task->type === 'bug' ? ['type' => 'bug'] : [])]);
        }

        $task->load([
            'project.workspace',
            'project.members.user',
            'taskStage',
            'assignedTo',
            'creator',
            'milestone',
            'epic:id,name,color',
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
            'epic_id' => ['nullable', \Illuminate\Validation\Rule::exists('epics', 'id')->where('project_id', $request->integer('project_id'))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'priority' => 'required|in:low,medium,high,critical',
            'story_points' => 'nullable|integer|min:0|max:100',
            // Dates are optional; a task may start and be due on the same day.
            'start_date' => 'nullable|date',
            'end_date' => ['nullable', 'date', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'assigned_to' => 'nullable|exists:users,id',
            'is_googlecalendar_sync' => 'nullable|boolean',
            ...$this->typeRules(),
        ]);
        $validated = $this->normaliseType($validated);

        // Ensure project belongs to current workspace
        $project = Project::find($validated['project_id']);
        if (!$project || $project->workspace_id != $workspace->id) {
            abort(403, 'Project not found in current workspace.');
        }

        // Shared with the AI assistant: creates the task in the first stage and
        // fires the created/assigned notifications.
        $task = app(\App\Actions\Tasks\CreateTask::class)->handle($user, $validated);

        // Sync with Google Calendar if enabled
        if ($validated['is_googlecalendar_sync'] ?? false) {
            $this->syncTaskWithGoogleCalendar($task);
        }

        return back()->with('success', $task->isBug() ? __('Bug reported successfully!') : __('Task created successfully!'));
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
            'story_points' => 'nullable|integer|min:0|max:100',
            // Dates are optional; a task may start and be due on the same day.
            'start_date' => 'nullable|date',
            'end_date' => ['nullable', 'date', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'assigned_to' => 'nullable|exists:users,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            // An epic of the same project.
            'epic_id' => ['nullable', \Illuminate\Validation\Rule::exists('epics', 'id')->where('project_id', $task->project_id)],
            'is_googlecalendar_sync' => 'boolean',
            ...$this->typeRules(),
        ]);
        $validated = $this->normaliseType($validated, $task);

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

        return back()->with('success', $task->isBug() ? __('Bug updated successfully!') : __('Task updated successfully!'));
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

        return back()->with('success', $task->isBug() ? __('Bug deleted successfully!') : __('Task deleted successfully!'));
    }

    public function duplicate(Task $task)
    {
        $this->authorizePermission('task_duplicate');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $task->project->workspace_id != $workspace->id) {
            abort(403, 'Task not found in current workspace.');
        }
        // A copy is a new item: it keeps the type and bug details, not the
        // link to the pre-merge bug record (unique to the original).
        $newTask = $task->replicate(['legacy_bug_id']);
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

        // Shared with the AI assistant: moves the task and fires the Slack event.
        app(\App\Actions\Tasks\ChangeTaskStage::class)->handle($user, $task, TaskStage::findOrFail($validated['task_stage_id']));

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
    /** A work item's type and, for bugs, its details. Shared by create and update. */
    private function typeRules(): array
    {
        return [
            'type' => 'nullable|in:' . implode(',', Task::TYPES),
            'severity' => 'nullable|in:' . implode(',', Task::SEVERITIES),
            'steps_to_reproduce' => 'nullable|string|max:10000',
            'expected_behavior' => 'nullable|string|max:5000',
            'actual_behavior' => 'nullable|string|max:5000',
            'environment' => 'nullable|string|max:255',
            'resolution_notes' => 'nullable|string|max:5000',
        ];
    }

    /**
     * A bug gets a severity (Major unless set); a plain task carries no bug details,
     * so switching a bug back to a task clears them.
     */
    private function normaliseType(array $validated, ?Task $task = null): array
    {
        $type = $validated['type'] ?? $task?->type ?? 'task';
        $validated['type'] = $type;
        if ($type === 'bug') {
            $validated['severity'] = $validated['severity'] ?? $task?->severity ?? 'major';
        } else {
            foreach (Task::BUG_FIELDS as $field) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

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
