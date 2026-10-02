<?php

namespace App\Http\Controllers;

use App\Models\GoogleMeeting;
use App\Models\Project;
use App\Models\User;
use App\Models\Setting;
use App\Services\GoogleMeetService;
use App\Services\GoogleCalendarService;
use App\Traits\HasPermissionChecks;
use App\Events\GoogleMeetingCreated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class GoogleMeetingController extends Controller
{
    use HasPermissionChecks;

    protected $googleMeetService;
    protected $googleCalendarService;

    public function __construct(GoogleMeetService $googleMeetService, GoogleCalendarService $googleCalendarService)
    {
        $this->googleMeetService = $googleMeetService;
        $this->googleCalendarService = $googleCalendarService;
    }

    public function index(Request $request)
    {
        $this->authorizePermission('google_meeting_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }

        $query = GoogleMeeting::with(['project', 'creator', 'workspace', 'members'])
            ->forWorkspace($workspace->id);
            
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Access control based on workspace role
        if ($userWorkspaceRole !== 'owner') {
            // Non-owners: Only assigned meetings or meetings they created
            $query->where(function($q) use ($user) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })->orWhere('user_id', $user->id);
            });
        }

        // Filter by search
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['title', 'status', 'start_time', 'duration', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        
        // Handle project sorting
        if ($sortBy === 'project.title') {
            $query->leftJoin('projects', 'google_meetings.project_id', '=', 'projects.id')
                  ->orderBy('projects.title', $sortOrder)
                  ->select('google_meetings.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = in_array($request->get('per_page', 10), [10, 25, 50, 100]) ? $request->get('per_page', 10) : 10;
        $meetings = $query->paginate($perPage)->withQueryString();

        // Get projects for filters and form
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);

        // Get workspace members for meeting assignment
        $members = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active');
        })->get(['id', 'name', 'email']);

        // Check if Google Meet is configured
        $hasGoogleMeetConfig = $this->googleMeetService->hasValidCredentials();
        
        // Get Google Calendar sync settings
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $user->id, $user->current_workspace_id) === '1';
        
        return Inertia::render('google-meetings/Index', [
            'meetings' => $meetings,
            'projects' => $projects,
            'members' => $members,
            'hasGoogleMeetConfig' => $hasGoogleMeetConfig,
            'filters' => $request->only(['search', 'status', 'project_id', 'sort_by', 'sort_order', 'per_page']),
            'permissions' => $this->getModuleCrudPermissions('google_meeting'),
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function create()
    {
        $this->authorizePermission('google_meeting_create');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }

        // Get projects for form
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);

        // Get workspace members
        $members = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active');
        })->get(['id', 'name', 'email']);

        // Get Google Calendar sync settings
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $user->id, $user->current_workspace_id) === '1';

        return Inertia::render('google-meetings/Create', [
            'projects' => $projects,
            'members' => $members,
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('google_meeting_create');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date|after:now',
            'duration' => 'required|integer|min:15|max:480',
            'project_id' => 'required|exists:projects,id',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        $startTime = Carbon::parse($request->start_time);
        
        // Preserve the user's timezone instead of converting to UTC
        if (!$startTime->getTimezone()) {
            $startTime->setTimezone(config('app.timezone', 'UTC'));
        }

        $meetingData = [
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $startTime->toISOString(),
            'duration' => $request->duration,
            'timezone' => $startTime->getTimezone()->getName(),
        ];

        try {
            // Create Google Meet through Google Calendar API
            $googleResponse = $this->googleMeetService->createMeeting($meetingData);
            
            $googleData = $googleResponse['data'];
            $meetingId = $googleData['id'];
            $joinUrl = $googleData['hangoutLink'];
            $startUrl = $googleData['hangoutLink'];

            $meeting = GoogleMeeting::create([
                'title' => $request->title,
                'description' => $request->description,
                'workspace_id' => $workspace->id,
                'project_id' => $request->project_id,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addMinutes($request->duration),
                'duration' => $request->duration,
                'join_url' => $joinUrl,
                'start_url' => $startUrl,
                'status' => 'scheduled',
                'user_id' => $user->id,
                'google_calendar_event_id' => $meetingId,
            ]);

            // Attach members to the meeting
            if (!empty($request->member_ids)) {
                $meeting->members()->attach($request->member_ids);
            }

            // Fire event for email notification
            if (!config('app.is_demo', true)) {
                event(new GoogleMeetingCreated($meeting));
            }

            return redirect()->route('google-meetings.index')
                ->with('success', __('Google Meet meeting created successfully!'));
                
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function show(GoogleMeeting $googleMeeting)
    {
        $this->authorizePermission('google_meeting_view');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $googleMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        $googleMeeting->load(['user', 'project', 'workspace', 'members']);

        // Transform avatar URLs
        $meetingData = $googleMeeting->toArray();
        if ($googleMeeting->user) {
            $meetingData['user']['avatar'] = check_file($googleMeeting->user->avatar)
                ? get_file($googleMeeting->user->avatar)
                : get_file('avatars/avatar.png');
        }
        if ($googleMeeting->members) {
            $meetingData['members'] = $googleMeeting->members->map(function ($member) {
                $data = $member->toArray();
                $data['avatar'] = check_file($member->avatar)
                    ? get_file($member->avatar)
                    : get_file('avatars/avatar.png');
                return $data;
            })->values()->toArray();
        }

        // Get projects for edit modal
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);
        
        // Get workspace members
        $members = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active');
        })->get(['id', 'name', 'email']);
        
        // Get Google Calendar sync settings
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $user->id, $user->current_workspace_id) === '1';

        return Inertia::render('google-meetings/Show', [
            'meeting' => $meetingData,
            'projects' => $projects,
            'members' => $members,
            'permissions' => $this->getModuleCrudPermissions('google_meeting'),
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function getProjectMembers($projectId)
    {
        $this->authorizePermission('google_meeting_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return response()->json(['error' => 'No workspace found'], 403);
        }

        $project = Project::find($projectId);
        
        if (!$project || $project->workspace_id != $workspace->id) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // Get all project users (members with roles) and clients
        $projectUsers = $project->users()->get();
        $projectClients = $project->clients()->get();
        
        $allUsers = collect();
        
        // Add project users (members/managers with their roles)
        $projectUsers->each(function ($user) use ($allUsers) {
            $allUsers->push([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role
            ]);
        });
        
        // Add clients
        $projectClients->each(function ($client) use ($allUsers) {
            $allUsers->push([
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'role' => 'client'
            ]);
        });

        return response()->json($allUsers->values());
    }

    public function update(Request $request, GoogleMeeting $googleMeeting)
    {
        $this->authorizePermission('google_meeting_update');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $googleMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:15|max:480',
            'project_id' => 'required|exists:projects,id',
            'status' => 'nullable|in:scheduled,started,ended,cancelled',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        $startTime = Carbon::parse($request->start_time);
        
        // Preserve the user's timezone instead of converting to UTC
        if (!$startTime->getTimezone()) {
            $startTime->setTimezone(config('app.timezone', 'UTC'));
        }
        
        $endTime = $startTime->copy()->addMinutes($request->duration);

        try {
            // Update in database
            $googleMeeting->update([
                'title' => $request->title,
                'description' => $request->description,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $request->duration,
                'project_id' => $request->project_id,
                'status' => $request->status ?? $googleMeeting->status,
            ]);

            // Update members
            if ($request->has('member_ids')) {
                $googleMeeting->members()->sync($request->member_ids ?? []);
            }

            // Sync with Google Calendar if event exists
            if ($googleMeeting->google_calendar_event_id && $this->googleCalendarService->isEnabled($user->id, $workspace->id)) {
                try {
                    $this->googleCalendarService->updateEvent($googleMeeting->google_calendar_event_id, $googleMeeting, $user->id, $workspace->id);
                } catch (\Exception $e) {
                    \Log::error('Failed to update Google Calendar event: ' . $e->getMessage());
                }
            }

            return redirect()->route('google-meetings.index')
                ->with('success', __('Google Meet meeting updated successfully!'));
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Error updating meeting: ') . $e->getMessage()]);
        }
    }

    public function destroy(GoogleMeeting $googleMeeting)
    {
        $this->authorizePermission('google_meeting_delete');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $googleMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        try {
            // Delete Google Calendar event if it exists
            if ($googleMeeting->google_calendar_event_id) {
                try {
                    $this->googleMeetService->deleteMeeting($googleMeeting->google_calendar_event_id);
                } catch (\Exception $e) {
                    \Log::error('Failed to delete Google Calendar event', [
                        'meeting_id' => $googleMeeting->id,
                        'event_id' => $googleMeeting->google_calendar_event_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $googleMeeting->delete();

            return redirect()->route('google-meetings.index')
                ->with('success', __('Meeting deleted successfully!'));
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Error deleting meeting: ') . $e->getMessage()]);
        }
    }

    public function join(GoogleMeeting $googleMeeting)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $googleMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        if (!$googleMeeting->join_url) {
            return back()->withErrors(['error' => __('Google Meet credentials not configured. Please configure Google Meet settings to enable video conferencing.')]);
        }

        return redirect()->away($googleMeeting->join_url);
    }

    public function calendar()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return response()->json([]);
        }

        $meetings = GoogleMeeting::with(['project', 'user'])
            ->forWorkspace($workspace->id)
            ->get()
            ->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start' => $meeting->start_time->toISOString(),
                    'end' => $meeting->end_time->toISOString(),
                    'backgroundColor' => '#10B77F',
                    'borderColor' => '#059669',
                    'extendedProps' => [
                        'type' => 'google_meeting',
                        'status' => $meeting->status,
                        'meet_url' => $meeting->meet_url,
                        'project' => $meeting->project?->title,
                        'description' => $meeting->description,
                        'duration' => $meeting->duration,
                    ]
                ];
            });

        return response()->json($meetings);
    }

}