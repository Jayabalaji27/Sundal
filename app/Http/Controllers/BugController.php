<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Project;
use App\Models\BugStatus;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Inertia\Inertia;
use Inertia\Response;

class BugController extends Controller
{
    use AuthorizesRequests, HasPermissionChecks;

    public function index(Request $request): Response
    {
        $this->authorizePermission('bug_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $userWorkspaceRole = $workspace->getMemberRole($user);

        $query = Bug::with(['project', 'bugStatus', 'assignedTo', 'reportedBy', 'milestone'])
            ->withCount(['comments', 'attachments'])
            ->forWorkspace($user->current_workspace_id)
            ->accessibleByUser($user, $userWorkspaceRole);

        if ($request->project_id) {
            $query->forProject($request->project_id);
        }

        if ($request->status_id) {
            $query->byStatus($request->status_id);
        }

        if ($request->priority) {
            $query->byPriority($request->priority);
        }

        if ($request->severity) {
            $query->bySeverity($request->severity);
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
            'severity',
            'created_at',
            'updated_at'
        ];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->latest(); // Default sorting
        }

        // Default to kanban view and get all data without pagination
        $view = $request->get('view', 'kanban');

        if ($view === 'kanban') {
            $bugs = $query->get();
        } else {
            $perPage = $request->get('per_page', 10);
            $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 10;
            $bugs = $query->paginate($perPage);
        }

        // Process avatars for bugs
        $bugCollection = ($bugs instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $bugs->getCollection() : $bugs;
        $bugCollection->each(function ($bug) {
            if ($bug->assignedTo) {
                $bug->assignedTo->avatar = check_file($bug->assignedTo->avatar)
                    ? get_file($bug->assignedTo->avatar)
                    : get_file('avatars/avatar.png');
            }
        });

        // Apply same access control to projects dropdown as used for bug filtering
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
        $statuses = BugStatus::forWorkspace($user->current_workspace_id)->ordered()->get();

        // Don't load all members by default - only load when project is selected
        $members = [];
        $milestones = [];

        if ($request->project_id) {
            $project = $projects->firstWhere('id', $request->project_id);
            if ($project) {
                $members = $project->members->filter(function ($member) {
                    return $member->user && $member->user->type !== 'client';
                })->pluck('user');
                $milestones = $project->milestones;
            }
        }

        // Get project name if filtering by project
        $projectName = null;
        if ($request->project_id) {
            $project = $projects->firstWhere('id', $request->project_id);
            $projectName = $project?->title ?? $request->project_name;
        }

        return Inertia::render('bugs/Index', [
            'bugs' => $bugs,
            'projects' => $projects,
            'statuses' => $statuses,
            'members' => $members,
            'milestones' => $milestones,
            'filters' => array_merge(
                $request->only(['project_id', 'status_id', 'priority', 'severity', 'assigned_to', 'search', 'per_page', 'sort_field', 'sort_direction']),
                ['view' => $view]
            ),
            'project_name' => $projectName,
            'userWorkspaceRole' => $userWorkspaceRole,
            'permissions' => [
                'create' => $this->checkPermission('bug_create'),
                'update' => $this->checkPermission('bug_update'),
                'delete' => $this->checkPermission('bug_delete'),
                'change_status' => $this->checkPermission('bug_change_status'),
                'assign_users' => $this->checkPermission('bug_assign'),
            ]
        ]);
    }

    public function show(Bug $bug)
    {
        $this->authorizePermission('bug_view');

        $currentUser = auth()->user();

        abort_if(
            ! \App\Models\Project::visibleTo($currentUser)->whereKey($bug->project_id)->exists(),
            404
        );

        $bug->load([
            'project.workspace',
            'project.members.user',
            'bugStatus',
            'assignedTo',
            'reportedBy',
            'resolvedBy',
            'milestone',
            'comments.user',
            'attachments.mediaItem'
        ]);

        // Ensure comments are ordered by creation date
        $bug->setRelation('comments', $bug->comments()->with('user')->latest()->get());

        $workspace = $currentUser->currentWorkspace;

        // Add permission flags to comments, attachments and bug
        $bug->comments->each(function ($comment) use ($currentUser) {
            $comment->can_update = $comment->canBeUpdatedBy($currentUser);
            $comment->can_delete = $comment->canBeDeletedBy($currentUser);
            if ($comment->user) {
                $comment->user->avatar = check_file($comment->user->avatar)
                    ? get_file($comment->user->avatar)
                    : get_file('avatars/avatar.png');
            }
        });

        $bug->attachments->each(function ($attachment) use ($currentUser) {
            $attachment->can_update = $attachment->canBeUpdatedBy($currentUser);
            $attachment->can_delete = $attachment->canBeDeletedBy($currentUser);
        });

        $bug->can_update = $bug->canBeUpdatedBy($currentUser);
        $bug->can_delete = $bug->canBeDeletedBy($currentUser);

        $allMembers = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        // Get project members only (no clients)
        $projectMembers = $bug->project->members->filter(function ($member) {
            return $member->user && $member->user->type !== 'client';
        })->pluck('user');

        $statuses = BugStatus::forWorkspace($currentUser->current_workspace_id)->ordered()->get();
        $milestones = $bug->project->milestones ?? [];

        return response()->json([
            'bug' => $bug,
            'members' => $projectMembers->isNotEmpty() ? $projectMembers : $allMembers,
            'statuses' => $statuses,
            'milestones' => $milestones,
            'permissions' => [
                'update' => $this->checkPermission('bug_update'),
                'delete' => $this->checkPermission('bug_delete'),
                'change_status' => $this->checkPermission('bug_change_status'),
                'assign_users' => $this->checkPermission('bug_assign'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('bug_create');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        $validated = $request->validate([
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) use ($user) {
                    $project = Project::find($value);
                    if (!$project || $project->workspace_id !== $user->current_workspace_id) {
                        $fail('The selected project is invalid or not accessible.');
                    }
                },
            ],
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'severity' => 'required|in:minor,major,critical,blocker',
            'steps_to_reproduce' => 'nullable|string',
            'expected_behavior' => 'nullable|string',
            'actual_behavior' => 'nullable|string',
            'environment' => 'nullable|string',
            'assigned_to' => 'nullable',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date'
        ]);

        // Clean up assigned_to if it's 'none' or empty
        if (isset($validated['assigned_to']) && ($validated['assigned_to'] === 'none' || $validated['assigned_to'] === '')) {
            $validated['assigned_to'] = null;
        }

        try {
            // Shared with the AI assistant: first status, reported_by, assignment email.
            app(\App\Actions\Bugs\CreateBug::class)->handle($user, $validated);

            return back()->with('success', __('Bug reported successfully!'));
        } catch (\App\Actions\ActionException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            \Log::error('Bug creation failed: ' . $e->getMessage());
            return back()->with('error', __('Unable to create bug. Please try again.'));
        }
    }

    public function update(Request $request, Bug $bug)
    {
        $this->authorizePermission('bug_update');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'severity' => 'required|in:minor,major,critical,blocker',
            'steps_to_reproduce' => 'nullable|string',
            'expected_behavior' => 'nullable|string',
            'actual_behavior' => 'nullable|string',
            'environment' => 'nullable|string',
            'assigned_to' => 'nullable',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'resolution_notes' => 'nullable|string'
        ]);

        // Clean up assigned_to if it's 'none' or empty
        if (isset($validated['assigned_to']) && ($validated['assigned_to'] === 'none' || $validated['assigned_to'] === '')) {
            $validated['assigned_to'] = null;
        }

        // Check if assigned_to changed
        $oldAssignedTo = $bug->assigned_to;
        $newAssignedTo = $validated['assigned_to'] ?? null;

        $bug->update($validated);

        // Fire event for email notification if bug assignment changed
        if ($newAssignedTo && $oldAssignedTo !== $newAssignedTo) {
            $assignedUser = \App\Models\User::find($newAssignedTo);
            if ($assignedUser) {
                $bug->load('project');
                if (!config('app.is_demo', true)) {
                    event(new \App\Events\BugAssigned($bug, $assignedUser, auth()->user()));
                }
            }
        }

        return back()->with('success', __('Bug updated successfully!'));
    }

    public function destroy(Bug $bug)
    {
        $this->authorizePermission('bug_delete');

        $bug->delete();

        return back()->with('success', __('Bug deleted successfully!'));
    }

    public function changeStatus(Request $request, Bug $bug)
    {
        $this->authorizePermission('bug_change_status');

        $validated = $request->validate([
            'bug_status_id' => 'required|exists:bug_statuses,id'
        ]);

        // Shared with the AI assistant: logs the change and records the resolver.
        app(\App\Actions\Bugs\ChangeBugStatus::class)->handle(auth()->user(), $bug, BugStatus::findOrFail($validated['bug_status_id']));

        return back()->with('success', __('Bug status updated successfully!'));
    }

    public function getProjectData(Request $request)
    {
        try {
            $projectId = $request->get('project_id');

            if (!$projectId) {
                return response()->json([
                    'success' => true,
                    'members' => [],
                    'milestones' => [],
                    'message' => 'No project selected'
                ]);
            }

            $user = auth()->user();
            $project = Project::with(['members.user', 'milestones'])
                ->where('workspace_id', $user->current_workspace_id)
                ->find($projectId);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'members' => [],
                    'milestones' => [],
                    'message' => 'Project not found or access denied'
                ]);
            }

            // Get only project members (not clients)
            $members = $project->members->filter(function ($member) {
                return $member->user && $member->user->type !== 'client';
            })->map(function ($member) {
                return [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email
                ];
            })->values();

            $milestones = $project->milestones->map(function ($milestone) {
                return [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'status' => $milestone->status
                ];
            });

            return response()->json([
                'success' => true,
                'members' => $members,
                'milestones' => $milestones,
                'project_name' => $project->name
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'members' => [],
                'milestones' => [],
                'message' => 'Error fetching project data: ' . $e->getMessage()
            ], 500);
        }
    }
}