<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TimesheetController extends Controller
{
    use HasPermissionChecks;
    /**
     * Check if user can access all workspace data based on role
     */
    private function canAccessAllData($user, $workspace): bool
    {
        $role = $workspace->getMemberRole($user);
        return $workspace->isOwner($user) || $role === 'company'; // Owner or Company workspace role: show all data all access
    }
    
    /**
     * Check if user can manage timesheets (approve/reject)
     */
    private function canManageTimesheets($user, $workspace): bool
    {
        $role = $workspace->getMemberRole($user);
        if ($workspace->isOwner($user) || $role === 'company') {
            return true; // Owner or Company workspace role: full access
        }
        
        return $role === 'manager'; // Manager: can manage
    }
    
    /**
     * Check if user can only view data (read-only)
     */
    private function isReadOnlyUser($user, $workspace): bool
    {
        $role = $workspace->getMemberRole($user);
        return $role === 'client'; // Client: only can see the data
    }
    
    /**
     * Get projects accessible to user based on workspace role
     */
    private function getAccessibleProjects($user, $workspace)
    {
        $role = $workspace->getMemberRole($user);
        
        if ($workspace->isOwner($user) || $role === 'company') {
            // Owner or Company workspace role: show all projects
            return Project::where('workspace_id', $workspace->id)
                ->with(['tasks' => function($query) {
                    $query->select('id', 'project_id', 'title');
                }])
                ->get();
        }
        
        // Other roles (Manager/Member/Client): only assigned projects
        return Project::where('workspace_id', $workspace->id)
            ->where(function($query) use ($user) {
                $query->whereHas('members', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orWhereHas('clients', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->orWhere('created_by', $user->id);
            })
            ->with(['tasks' => function($query) {
                $query->select('id', 'project_id', 'title');
            }])
            ->get();
    }

    /**
     * Scope a TimesheetEntry query to what a given role is allowed to see, using
     * the same rule for every page that reports "this user's hours" — owner sees
     * everything, manager/client see entries on projects they're attached to, and
     * member sees their own timesheets or entries on tasks assigned to them.
     * Previously weeklyView() scoped by project *visibility* instead (any entry on
     * a visible project, including other people's), which is why its weekly total
     * didn't match the "This Week" figure on the main Timesheets index.
     */
    private function scopeEntriesToUser($query, $user, $workspace)
    {
        // "My Timesheets" / Daily / Weekly / Monthly views: own data unless the user
        // can access all workspace data (owner). Managers review other people's
        // timesheets under Approvals, not here.
        if ($this->canAccessAllData($user, $workspace)) {
            return $query;
        }

        return $query->whereHas('timesheet', fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * Parse an entry's start/end into Carbon instances (datetime or time-only input).
     */
    private function parseEntryTimes(array $entryData, string $fallbackDate): array
    {
        if (strpos($entryData['start_time'], 'T') !== false) {
            return [Carbon::parse($entryData['start_time']), Carbon::parse($entryData['end_time'])];
        }

        return [
            Carbon::parse($fallbackDate . ' ' . $entryData['start_time']),
            Carbon::parse($fallbackDate . ' ' . $entryData['end_time']),
        ];
    }

    /**
     * Server-side hour rules for timesheet entries: end after start (no 0h entries),
     * at least 0.25h, and no more than 24h logged by the user on any one day.
     */
    private function ensureValidEntryHours(array $entries, string $fallbackDate, int $userId, array $replacedEntryIds = []): void
    {
        $errors = [];
        $hoursByDate = [];

        foreach ($entries as $i => $entryData) {
            [$startTime, $endTime] = $this->parseEntryTimes($entryData, $fallbackDate);

            if ($endTime->lte($startTime)) {
                $errors["entries.$i.end_time"] = __('The end time must be after the start time.');
                continue;
            }

            $hours = $startTime->diffInMinutes($endTime) / 60;
            if ($hours < TimesheetEntry::MIN_HOURS) {
                $errors["entries.$i.end_time"] = __('An entry must be at least 15 minutes long.');
                continue;
            }

            $date = $startTime->toDateString();
            $hoursByDate[$date] = ($hoursByDate[$date] ?? 0) + $hours;
        }

        foreach ($hoursByDate as $date => $hours) {
            $total = $hours + TimesheetEntry::hoursLoggedOn($userId, $date, $replacedEntryIds);
            if ($total > TimesheetEntry::MAX_HOURS_PER_DAY) {
                $errors['entries'] = __('Total hours for :date would be :total. A day can have at most 24 hours.', [
                    'date' => $date,
                    'total' => round($total, 2),
                ]);
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    public function index(Request $request)
    {
        $this->authorizePermission('timesheet_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        if (!$workspace) {
            return redirect()->route('dashboard')->withErrors(['message' => 'No workspace selected']);
        }
        
        $query = Timesheet::with(['user', 'entries.project', 'entries.task', 'approver'])
            ->where('workspace_id', $workspace->id);

        // Own timesheets only, unless the user can access all workspace data (owner).
        $canAccessAllData = $this->canAccessAllData($user, $workspace);
        if (!$canAccessAllData) {
            $query->where('user_id', $user->id);
        }

        // Apply filters
        if ($request->status && $request->status !== 'all') {
            $query->byStatus($request->status);
        }

        if ($request->user_id && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('user', function($userQuery) use ($request) {
                    $userQuery->where('name', 'like', '%' . $request->search . '%');
                })
                ->orWhere('notes', 'like', '%' . $request->search . '%')
                ->orWhereHas('entries', function($entryQuery) use ($request) {
                    $entryQuery->where('description', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->project_id && $request->project_id !== 'all') {
            $query->whereHas('entries', function($q) use ($request) {
                $q->where('project_id', $request->project_id);
            });
        }

        if ($request->start_date) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('end_date', '<=', $request->end_date);
        }

        if ($request->is_billable && $request->is_billable !== 'all') {
            $isBillable = $request->is_billable === '1';
            $query->whereHas('entries', function($q) use ($isBillable) {
                $q->where('is_billable', $isBillable);
            });
        }

        if ($request->min_hours) {
            $query->where('total_hours', '>=', $request->min_hours);
        }

        if ($request->max_hours) {
            $query->where('total_hours', '<=', $request->max_hours);
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'start_date', 'end_date', 'status', 'total_hours', 'billable_hours', 'user_id'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Apply sorting
        if ($sortField === 'user_id') {
            $query->join('users', 'timesheets.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortDirection)
                  ->select('timesheets.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }
        
        // Handle per_page parameter with validation
        $perPage = $request->get('per_page', 12);
        $perPage = in_array($perPage, [12, 24, 48, 100]) ? $perPage : 12;
        
        $timesheets = $query->paginate($perPage)->withQueryString();

        // Member filter - only useful when the list spans more than one person
        $members = collect();
        if ($canAccessAllData) {
            $members = User::whereHas('workspaces', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id)->where('status', 'active');
            })->get(['id', 'name', 'email', 'avatar']);
        }

        // Get projects based on role permissions
        $projects = $this->getAccessibleProjects($user, $workspace);

        // Calculate overview statistics based on workspace role permissions
        $statsQuery = Timesheet::where('workspace_id', $workspace->id);
        $hoursQuery = $this->scopeEntriesToUser(
            TimesheetEntry::whereHas('timesheet', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            }),
            $user,
            $workspace
        );

        if (!$canAccessAllData) {
            $statsQuery->where('user_id', $user->id);
        }

        $overviewStats = [
            'total_timesheets' => $timesheets->total(),
            'draft_count' => $statsQuery->clone()->where('status', 'draft')->count(),
            'submitted_count' => $statsQuery->clone()->where('status', 'submitted')->count(),
            'approved_count' => $statsQuery->clone()->where('status', 'approved')->count(),
            'total_hours_this_week' => $hoursQuery->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->sum('hours')
        ];

        // user.avatar
        $timesheets->each(function ($timesheet) {
            $timesheet->user->avatar = check_file($timesheet->user->avatar) ? get_file($timesheet->user->avatar) : get_file('avatars/avatar.png');
        });

        return Inertia::render('timesheets/Index', [
            'timesheets' => $timesheets,
            'members' => $members,
            'projects' => $projects,
            'overviewStats' => $overviewStats,
            'filters' => $request->only([
                'status', 'user_id', 'search', 'project_id', 
                'start_date', 'end_date', 'is_billable', 
                'min_hours', 'max_hours', 'per_page', 'view', 'sort_field', 'sort_direction'
            ]),
            'permissions' => [
                'canAccessAllData' => $this->canAccessAllData($user, $workspace),
                'canManageTimesheets' => $this->canManageTimesheets($user, $workspace),
                'isReadOnly' => $this->isReadOnlyUser($user, $workspace),
                'userRole' => $workspace->isOwner($user) ? 'owner' : $workspace->getMemberRole($user),
                'create' => $this->checkPermission('timesheet_create'),
                'update' => $this->checkPermission('timesheet_update'),
                'delete' => $this->checkPermission('timesheet_delete'),
                'submit' => $this->checkPermission('timesheet_submit'),
                'approve' => $this->checkPermission('timesheet_approve'),
                'reject' => $this->checkPermission('timesheet_approve'),
                'useTimer' => $this->checkPermission('timesheet_use_timer'),
            ],
            'userWorkspaceRole' => $workspace->isOwner($user) ? 'owner' : $workspace->getMemberRole($user),
            'timerStatus' => [
                'active' => $user->timer_active,
                'project_id' => $user->timer_project_id,
                'task_id' => $user->timer_task_id,
                'description' => $user->timer_description,
                'started_at' => $user->timer_started_at,
                'entry_id' => $user->timer_entry_id
            ]
        ]);
    }

    public function show(Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_view');
                
        $timesheet->load(['entries.project', 'entries.task', 'entries.user', 'approvals.approver']);
        
        $projects = Project::where('workspace_id', auth()->user()->current_workspace_id)
            ->with('tasks')
            ->get();
                
        return Inertia::render('timesheets/Show', [
            'timesheet' => $timesheet,
            'projects' => $projects,
            'permissions' => [
                'update' => $this->checkPermission('timesheet_update'),
                'delete' => $this->checkPermission('timesheet_delete'),
                'submit' => $this->checkPermission('timesheet_submit'),
                'approve' => $this->checkPermission('timesheet_approve'),
                'reject' => $this->checkPermission('timesheet_approve'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('timesheet_create');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'notes' => 'nullable|string',
            'entries' => 'array',
            'entries.*.project_id' => 'required|exists:projects,id',
            'entries.*.task_id' => 'nullable|exists:tasks,id',
            'entries.*.start_time' => 'required',
            'entries.*.end_time' => 'required',
            'entries.*.description' => 'nullable|string'
        ]);

        // The week is always derived from start_date server-side, never trusted from
        // the client as an arbitrary start/end pair — that's how this workspace ended
        // up with single-day and 11-day "week" timesheets (see RepairTimesheetWeeks).
        $weekStart = \Carbon\Carbon::parse($validated['start_date'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $timesheet = Timesheet::firstOrCreate(
            [
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
            ],
            [
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'total_hours' => 0,
                'billable_hours' => 0,
            ]
        );

        $this->ensureValidEntryHours($validated['entries'] ?? [], $validated['start_date'], $user->id);

        // Create entries if provided
        if (!empty($validated['entries'])) {
            foreach ($validated['entries'] as $entryData) {
                // Handle both datetime and time formats
                if (strpos($entryData['start_time'], 'T') !== false) {
                    // Datetime format from frontend
                    $startTime = \Carbon\Carbon::parse($entryData['start_time']);
                    $endTime = \Carbon\Carbon::parse($entryData['end_time']);
                } else {
                    // Time format
                    $date = $validated['start_date'];
                    $startTime = \Carbon\Carbon::parse($date . ' ' . $entryData['start_time']);
                    $endTime = \Carbon\Carbon::parse($date . ' ' . $entryData['end_time']);
                }
                
                $hours = $startTime->diffInMinutes($endTime) / 60;
                
                TimesheetEntry::create([
                    'timesheet_id' => $timesheet->id,
                    'project_id' => $entryData['project_id'],
                    'task_id' => ($entryData['task_id'] === 'none') ? null : $entryData['task_id'],
                    'user_id' => auth()->id(),
                    'date' => $startTime->toDateString(),
                    'start_time' => $startTime->format('H:i'),
                    'end_time' => $endTime->format('H:i'),
                    'hours' => $hours,
                    'description' => $entryData['description'],
                    'is_billable' => true,
                    'hourly_rate' => 0
                ]);
            }
            $timesheet->calculateTotals();
        }

        return back()->with('success', __('Timesheet created successfully!'));
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_update');
        
        $validated = $request->validate([
            'start_date' => 'required|date',
            'notes' => 'nullable|string',
            'entries' => 'array',
            'entries.*.id' => 'nullable|exists:timesheet_entries,id',
            'entries.*.project_id' => 'required|exists:projects,id',
            'entries.*.task_id' => 'nullable|exists:tasks,id',
            'entries.*.start_time' => 'required',
            'entries.*.end_time' => 'required',
            'entries.*.description' => 'nullable|string'
        ]);

        // Entries kept by this update replace the timesheet's current ones, so only
        // the user's entries outside this timesheet count towards the daily limit.
        $this->ensureValidEntryHours(
            $validated['entries'] ?? [],
            $validated['start_date'],
            $timesheet->user_id,
            $timesheet->entries()->pluck('id')->all()
        );

        // Same rule as store(): the week is always derived from start_date, never
        // trusted as an arbitrary start/end pair from the client.
        $weekStart = \Carbon\Carbon::parse($validated['start_date'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $timesheet->update([
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekEnd->toDateString(),
            'notes' => $validated['notes']
        ]);

        // Handle entries if provided
        if (isset($validated['entries'])) {
            $existingEntryIds = [];
            
            foreach ($validated['entries'] as $entryData) {
                // Handle both datetime and time formats
                if (strpos($entryData['start_time'], 'T') !== false) {
                    // Datetime format from frontend
                    $startTime = \Carbon\Carbon::parse($entryData['start_time']);
                    $endTime = \Carbon\Carbon::parse($entryData['end_time']);
                } else {
                    // Time format
                    $date = $validated['start_date'];
                    $startTime = \Carbon\Carbon::parse($date . ' ' . $entryData['start_time']);
                    $endTime = \Carbon\Carbon::parse($date . ' ' . $entryData['end_time']);
                }
                
                $hours = $startTime->diffInMinutes($endTime) / 60;
                
                if (!empty($entryData['id'])) {
                    // Update existing entry
                    $entry = $timesheet->entries()->find($entryData['id']);
                    if ($entry) {
                        $entry->update([
                            'project_id' => $entryData['project_id'],
                            'task_id' => ($entryData['task_id'] === 'none') ? null : $entryData['task_id'],
                            'date' => $startTime->toDateString(),
                            'start_time' => $startTime->format('H:i'),
                            'end_time' => $endTime->format('H:i'),
                            'hours' => $hours,
                            'description' => $entryData['description'],
                            'is_billable' => true
                        ]);
                        $existingEntryIds[] = $entry->id;
                    }
                } else {
                    // Create new entry
                    $entry = TimesheetEntry::create([
                        'timesheet_id' => $timesheet->id,
                        'project_id' => $entryData['project_id'],
                        'task_id' => ($entryData['task_id'] === 'none') ? null : $entryData['task_id'],
                        'user_id' => $timesheet->user_id, // Use timesheet owner's ID
                        'date' => $startTime->toDateString(),
                        'start_time' => $startTime->format('H:i'),
                        'end_time' => $endTime->format('H:i'),
                        'hours' => $hours,
                        'description' => $entryData['description'],
                        'is_billable' => true,
                        'hourly_rate' => 0
                    ]);
                    $existingEntryIds[] = $entry->id;
                }
            }
            
            // Delete entries that were removed
            $timesheet->entries()->whereNotIn('id', $existingEntryIds)->delete();
        }

        $timesheet->calculateTotals();
        return back()->with('success', __('Timesheet updated successfully!'));
    }

    public function destroy(Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_delete');
        
        $timesheet->delete();
        return back()->with('success', __('Timesheet deleted successfully!'));
    }

    public function submit(Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_submit');

        // Only the person who logged the time submits it; owners/managers approve or
        // reject it afterwards.
        if ((int) $timesheet->user_id !== (int) auth()->id()) {
            abort(403, __('You can only submit your own timesheets.'));
        }

        if ($timesheet->status !== 'draft') {
            return back()->withErrors(['message' => 'Only draft timesheets can be submitted']);
        }
        
        if (!$timesheet->entries()->exists()) {
            return back()->withErrors(['message' => 'Cannot submit timesheet without entries']);
        }

        try {
            $timesheet->update([
                'status' => 'submitted',
                'submitted_at' => now()
            ]);

            // Create approval record for workspace owner only
            $workspace = $timesheet->workspace;
            
            if ($workspace->owner) {
                \App\Models\TimesheetApproval::create([
                    'timesheet_id' => $timesheet->id,
                    'approver_id' => $workspace->owner->id,
                    'status' => 'pending'
                ]);
            }

            return back()->with('success', __('Timesheet submitted successfully!'));
        } catch (\Exception $e) {
            return back()->withErrors(['message' => 'Failed to submit timesheet: ' . $e->getMessage()]);
        }
    }

    public function approve(Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_approve');
        
        $user = auth()->user();
        
        if (!$timesheet->canApprove()) {
            return back()->withErrors(['message' => 'Cannot approve timesheet']);
        }

        $timesheet->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id
        ]);

        return back()->with('success', __('Timesheet approved successfully!'));
    }

    public function reject(Request $request, Timesheet $timesheet)
    {
        $this->authorizePermission('timesheet_approve');
        
        $validated = $request->validate([
            'reason' => 'required|string'
        ]);

        $timesheet->update([
            'status' => 'rejected',
            'notes' => $validated['reason']
        ]);

        return back()->with('success', __('Timesheet rejected successfully!'));
    }

    public function dailyView(Request $request)
    {
        $this->authorizePermission('timesheet_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $date = $request->date ? Carbon::parse($request->date) : now();
        
        if (!$workspace) {
            return redirect()->route('dashboard')->withErrors(['message' => 'No workspace selected']);
        }
        
        $role = $workspace->getMemberRole($user);
        $isDemoMode = config('app.is_demo', false);
        
        // Build query for entries for the selected date based on workspace role
        $query = $this->scopeEntriesToUser(
            TimesheetEntry::with(['project', 'task', 'timesheet'])
                ->whereHas('timesheet', function($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                }),
            $user,
            $workspace
        );
        
        if (!$isDemoMode) {
            $query->whereDate('date', $date);
        }

        // Apply search filter
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', '%' . $request->search . '%')
                  ->orWhereHas('project', function($projectQuery) use ($request) {
                      $projectQuery->where('title', 'like', '%' . $request->search . '%');
                  })
                  ->orWhereHas('task', function($taskQuery) use ($request) {
                      $taskQuery->where('title', 'like', '%' . $request->search . '%');
                  });
            });
        }

        // Apply project filter
        if ($request->project && $request->project !== 'all') {
            $query->where('project_id', $request->project);
        }

        // Apply billable filter
        if ($request->billable && $request->billable !== 'all') {
            $isBillable = in_array($request->billable, ['1', 'true', true], true);
            $query->where('is_billable', $isBillable);
        }

        // Handle per_page parameter with validation
        $perPage = $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50]) ? $perPage : 10;
        
        $entries = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $projects = $this->getAccessibleProjects($user, $workspace);
        
        // Get or create a timesheet for the current period
        $timesheet = $user->timesheets()
            ->where('workspace_id', $workspace->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
            
        if (!$timesheet) {
            $timesheet = Timesheet::create([
                'user_id' => $user->id,
                'workspace_id' => $workspace->id,
                'start_date' => $date->copy()->startOfWeek(),
                'end_date' => $date->copy()->endOfWeek(),
                'status' => 'draft',
                'total_hours' => 0,
                'billable_hours' => 0
            ]);
        }
        
        $timesheetId = $timesheet->id;

        return Inertia::render('timesheets/DailyView', [
            'entries' => $entries,
            'projects' => $projects,
            'selectedDate' => $date->toDateString(),
            'timesheetId' => $timesheetId,
            'filters' => $request->only([
                'search', 'project', 'billable', 'per_page'
            ]),
            'permissions' => [
                'canAccessAllData' => $workspace->isOwner($user) || $role === 'admin',
                'canManageTimesheets' => $workspace->isOwner($user) || $role === 'admin',
                'isReadOnly' => $role === 'client',
                'userRole' => $workspace->isOwner($user) ? 'owner' : $role,
                'useTimer' => $this->checkPermission('timesheet_use_timer')
            ],
            'timerStatus' => [
                'active' => $user->timer_active,
                'project_id' => $user->timer_project_id,
                'task_id' => $user->timer_task_id,
                'description' => $user->timer_description,
                'started_at' => $user->timer_started_at,
                'entry_id' => $user->timer_entry_id
            ]
        ]);
    }

    public function weeklyView(Request $request)
    {
        $this->authorizePermission('timesheet_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $weekStart = $request->week_start ? Carbon::parse($request->week_start) : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        if (!$workspace) {
            return redirect()->route('dashboard')->withErrors(['message' => 'No workspace selected']);
        }
        
        $isDemoMode = config('app.is_demo', false);

        $weekData = [];
        for ($date = $weekStart->copy(); $date <= $weekEnd; $date->addDay()) {
            $entriesQuery = $this->scopeEntriesToUser(
                TimesheetEntry::with(['project', 'task'])
                    ->whereHas('timesheet', function($q) use ($workspace) {
                        $q->where('workspace_id', $workspace->id);
                    }),
                $user,
                $workspace
            );

            // if (!$isDemoMode) {
                $entriesQuery->whereDate('date', $date);
            // }

            $entries = $entriesQuery->get();
                
            $weekData[] = [
                'date' => $date->toDateString(),
                'entries' => $entries,
                'totalHours' => $entries->sum('hours'),
                'billableHours' => $entries->where('is_billable', true)->sum('hours')
            ];
        }

        $projects = $this->getAccessibleProjects($user, $workspace);
        // This user's timesheet for the week being viewed (null if they have none yet)
        $timesheetId = $user->timesheets()
            ->where('workspace_id', $workspace->id)
            ->whereDate('start_date', '<=', $weekStart->toDateString())
            ->whereDate('end_date', '>=', $weekStart->toDateString())
            ->value('id');

        return Inertia::render('timesheets/WeeklyView', [
            'weekData' => $weekData,
            'projects' => $projects,
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'timesheetId' => $timesheetId,
            'permissions' => [
                'canAccessAllData' => $this->canAccessAllData($user, $workspace),
                'canManageTimesheets' => $this->canManageTimesheets($user, $workspace),
                'isReadOnly' => $this->isReadOnlyUser($user, $workspace),
                'userRole' => $workspace->isOwner($user) ? 'owner' : $workspace->getMemberRole($user),
                'useTimer' => $this->checkPermission('timesheet_use_timer')
            ]
        ]);
    }

    public function monthlyView(Request $request)
    {
        $this->authorizePermission('timesheet_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $month = $request->month ? Carbon::parse($request->month . '-01') : now()->startOfMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        
        if (!$workspace) {
            return redirect()->route('dashboard')->withErrors(['message' => 'No workspace selected']);
        }

        $role = $workspace->getMemberRole($user);
        $isDemoMode = config('app.is_demo', false);

        $entriesQuery = $this->scopeEntriesToUser(
            TimesheetEntry::with(['project', 'task'])
                ->whereHas('timesheet', function($q) use ($workspace) {
                    $q->where('workspace_id', $workspace->id);
                }),
            $user,
            $workspace
        );

        if (!$isDemoMode) {
            $entriesQuery->whereBetween('date', [$monthStart, $monthEnd]);
        }
        
        $entries = $entriesQuery->get();

        $monthData = [
            'total_hours' => $entries->sum('hours'),
            'billable_hours' => $entries->where('is_billable', true)->sum('hours'),
            'entries_count' => $entries->count(),
            'working_days' => $monthStart->diffInWeekdays($monthEnd)
        ];

        $projectBreakdown = $entries->groupBy('project.title')->map(function($projectEntries, $projectName) use ($monthData) {
            $totalHours = $projectEntries->sum('hours');
            return [
                'project_name' => $projectName,
                'total_hours' => $totalHours,
                'billable_hours' => $projectEntries->where('is_billable', true)->sum('hours'),
                'percentage' => $monthData['total_hours'] > 0 ? ($totalHours / $monthData['total_hours']) * 100 : 0
            ];
        })->values();

        $weeklyBreakdown = [];
        for ($week = $monthStart->copy(); $week <= $monthEnd; $week->addWeek()) {
            $weekEnd = $week->copy()->endOfWeek();
            if ($weekEnd > $monthEnd) $weekEnd = $monthEnd;
            
            $weekEntries = $entries->whereBetween('date', [$week, $weekEnd]);
            $weeklyBreakdown[] = [
                'week_start' => $week->toDateString(),
                'week_end' => $weekEnd->toDateString(),
                'total_hours' => $weekEntries->sum('hours'),
                'billable_hours' => $weekEntries->where('is_billable', true)->sum('hours'),
                'entries_count' => $weekEntries->count()
            ];
        }

        $projects = $this->getAccessibleProjects($user, $workspace);
        $timesheetId = $user->timesheets()->where('workspace_id', $workspace->id)->first()->id ?? 1;

        return Inertia::render('timesheets/MonthlyView', [
            'monthData' => $monthData,
            'projectBreakdown' => $projectBreakdown,
            'weeklyBreakdown' => $weeklyBreakdown,
            'projects' => $projects,
            'currentMonth' => $month->format('Y-m'),
            'timesheetId' => $timesheetId,
            'permissions' => [
                'canAccessAllData' => $workspace->isOwner($user) || $role === 'admin',
                'canManageTimesheets' => $workspace->isOwner($user) || $role === 'admin',
                'isReadOnly' => $role === 'client',
                'userRole' => $workspace->isOwner($user) ? 'owner' : $role,
                'useTimer' => $this->checkPermission('timesheet_use_timer')
            ]
        ]);
    }

    public function calendarView(Request $request)
    {
        $this->authorizePermission('timesheet_view_any');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        $month = $request->month ? Carbon::parse($request->month . '-01') : now();
        $monthStart = $month->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $month->copy()->endOfMonth()->endOfWeek();
        
        if (!$workspace) {
            return redirect()->route('dashboard')->withErrors(['message' => 'No workspace selected']);
        }
        
        $role = $workspace->getMemberRole($user);
        
        // Determine if user can access all data: workspace owner OR company workspace role
        $canAccessAllData = $workspace->isOwner($user) || $role === 'company';
        $isDemoMode = config('app.is_demo', false);
        
        $calendarData = [];
        for ($date = $monthStart->copy(); $date <= $monthEnd; $date->addDay()) {
            $query = TimesheetEntry::with(['project', 'task', 'user'])
                ->whereHas('timesheet', function($q) use ($workspace, $user, $canAccessAllData) {
                    $q->where('workspace_id', $workspace->id);
                    // If user cannot access all data, restrict to their own entries or tasks assigned to them
                    if (!$canAccessAllData) {
                        $q->where(function($tq) use ($user) {
                            $tq->where('user_id', $user->id)
                               ->orWhereHas('entries', function($entryQuery) use ($user) {
                                   $entryQuery->whereHas('task', function($taskQuery) use ($user) {
                                       $taskQuery->where(function($tQuery) use ($user) {
                                           $tQuery->where('assigned_to', $user->id)
                                                  ->orWhereHas('members', function($memberQuery) use ($user) {
                                                      $memberQuery->where('user_id', $user->id);
                                                  });
                                       });
                                   });
                               });
                        });
                    }
                });

            // if (!$isDemoMode) {
                $query->whereDate('date', $date);
            // }

            $entries = $query->get();
                
            $calendarData[] = [
                'date' => $date->toDateString(),
                'entries' => $entries,
                'totalHours' => $entries->sum('hours'),
                'isCurrentMonth' => $date->month === $month->month,
                'isToday' => $date->isToday()
            ];
        }

        $projects = $this->getAccessibleProjects($user, $workspace);
        $timesheetId = $user->timesheets()->where('workspace_id', $workspace->id)->first()->id ?? 1;
        
        // Get system settings for Google Calendar integration
        $googleCalendarSyncStatus = getSetting('is_googlecalendar_sync', '0', $user->id, $workspace->id);
        
        // Also check directly from database for debugging
        $directDbCheck = \App\Models\Setting::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('key', 'is_googlecalendar_sync')
            ->value('value');
            
        $systemSettings = [
            'is_googlecalendar_sync' => $googleCalendarSyncStatus
        ];
        


        return Inertia::render('timesheets/CalendarView', [
            'calendarData' => $calendarData,
            'currentMonth' => $month->format('Y-m'),
            'projects' => $projects,
            'timesheetId' => $timesheetId,
            'systemSettings' => $systemSettings,
            'permissions' => [
                'canAccessAllData' => $canAccessAllData,
                'canManageTimesheets' => $workspace->isOwner($user) || $role === 'company',
                'isReadOnly' => $role === 'client',
                'userRole' => $workspace->isOwner($user) ? 'owner' : $role,
                'useTimer' => $this->checkPermission('timesheet_use_timer')
            ]
        ]);
    }

    public function approvals(Request $request)
    {
        $this->authorizePermission('timesheet_approve');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = \App\Models\TimesheetApproval::with(['timesheet.user', 'timesheet.entries'])
            ->whereHas('timesheet', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            });

        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Handle per_page parameter with validation
        $perPage = $request->get('per_page', 20);
        $perPage = in_array($perPage, [20, 50, 100]) ? $perPage : 20;
        
        $approvals = $query->latest()->paginate($perPage);

        $approvals->getCollection()->transform(function ($approval) {
            if ($approval->timesheet?->user) {
                $approval->timesheet->user->avatar = check_file($approval->timesheet->user->avatar)
                    ? get_file($approval->timesheet->user->avatar)
                    : get_file('avatars/avatar.png');
            }
            return $approval;
        });

        return Inertia::render('timesheets/Approvals', [
            'approvals' => $approvals,
            'filters' => $request->only(['status', 'per_page', 'view'])
        ]);
    }

    public function reports(Request $request)
    {
        $this->authorizePermission('report_timesheet');
        
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $members = User::whereHas('workspaces', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)->where('status', 'active');
        })->get();

        $projects = Project::where(function($query) use ($user, $workspace) {
            $query->where('workspace_id', $workspace->id)
                  ->orWhereHas('members', function($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })->get();

        return Inertia::render('timesheets/Reports', [
            'members' => $members,
            'projects' => $projects
        ]);
    }


}