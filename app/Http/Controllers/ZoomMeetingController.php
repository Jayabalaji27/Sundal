<?php

namespace App\Http\Controllers;

use App\Models\ZoomMeeting;
use App\Models\Project;
use App\Models\User;
use App\Models\Setting;
use App\Services\ZoomService;
use App\Services\GoogleCalendarService;
use App\Traits\HasPermissionChecks;
use App\Events\ZoomMeetingCreated;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;


class ZoomMeetingController extends Controller
{
    use HasPermissionChecks;

    protected $zoomService;
    protected $googleCalendarService;

    public function __construct(ZoomService $zoomService, GoogleCalendarService $googleCalendarService)
    {
        $this->zoomService = $zoomService;
        $this->googleCalendarService = $googleCalendarService;
    }

    public function index(Request $request)
    {
        $this->authorizePermission('zoom_meeting_view_any');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            abort(404, __('No workspace found. Please select a workspace.'));
        }

        $query = ZoomMeeting::with(['project', 'creator', 'workspace', 'members'])
            ->forWorkspace($workspace->id);
            
        $userWorkspaceRole = $workspace->getMemberRole($user);
        
        // Access control based on workspace role - same as ProjectController
        if ($userWorkspaceRole !== 'owner') {
            // Non-owners: Only assigned meetings or meetings they created
            $query->where(function($q) use ($user) {
                $q->whereHas('members', function($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                })->orWhere('user_id', $user->id);
            });
        }
        // Filter by search
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by project
        if ($request->project_id && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        
        // Validate sort fields
        $allowedSortFields = ['created_at', 'title', 'start_time', 'duration', 'status'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Apply sorting
        $query->orderBy($sortField, $sortDirection);

        $perPage = in_array($request->get('per_page', 10), [10, 25, 50, 100]) ? $request->get('per_page', 10) : 10;
        $meetings = $query->paginate($perPage);



        // Get projects for filters and form
        $projects = Project::forWorkspace($workspace->id)->get(['id', 'title']);

        // Get workspace members for meeting assignment
        $members = User::whereHas('workspaces', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id)
              ->where('status', 'active');
        })->get(['id', 'name', 'email']);

        // Check if Zoom is configured (now works for all users by checking workspace-level settings)
        $hasZoomConfig = $this->zoomService->hasValidCredentials();
        
        // Get Google Calendar sync settings
        $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $user->id, $user->current_workspace_id) === '1';
        
        return Inertia::render('zoom-meetings/Index', [
            'meetings' => $meetings,
            'projects' => $projects,
            'members' => $members,
            'hasZoomConfig' => $hasZoomConfig,
            'filters' => $request->only(['search', 'status', 'project_id', 'per_page', 'sort_field', 'sort_direction']),
            'permissions' => $this->getModuleCrudPermissions('zoom_meeting'),
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('zoom_meeting_create');

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
            'timezone' => 'required|string',
            'password' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'member_ids' => 'required|array',
            'member_ids.*' => 'exists:users,id',
            'is_googlecalendar_sync' => 'nullable|boolean',
        ]);

        $startTime = Carbon::parse($request->start_time);

        $meetingData = [
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $startTime->toISOString(),
            'duration' => $request->duration,
            'timezone' => $request->timezone,
            'password' => $request->password,
        ];

        try {
            // Check if Zoom credentials are configured
            $hasCredentials = $this->zoomService->hasValidCredentials();
            
            if ($hasCredentials) {
                // Create meeting via Zoom API
                $zoomResponse = $this->zoomService->createMeeting($meetingData);

                if ($zoomResponse['success']) {
                    $zoomData = $zoomResponse['data'];
                    $meetingId = $zoomData['id'];
                    $startUrl = $zoomData['start_url'];
                    $joinUrl = $zoomData['join_url'];
                    $password = $zoomData['password'] ?? $request->password;
                } else {
                    // Fallback to local meeting if API fails
                    $meetingId = 'local_' . time();
                    $startUrl = null;
                    $joinUrl = null;
                    $password = $request->password;
                }
            } else {
                // Create local meeting when no Zoom credentials
                $meetingId = 'local_' . time();
                $startUrl = null;
                $joinUrl = null;
                $password = $request->password;
            }

            $meeting = ZoomMeeting::create([
                'title' => $request->title,
                'description' => $request->description,
                'workspace_id' => $workspace->id,
                'zoom_meeting_id' => $meetingId,
                'project_id' => $request->project_id,
                'start_time' => $startTime,
                'end_time' => $startTime->copy()->addMinutes($request->duration),
                'duration' => $request->duration,
                'timezone' => $request->timezone,
                'start_url' => $startUrl,
                'password' => $password,
                'join_url' => $joinUrl,
                'status' => 'scheduled',
                'user_id' => $user->id,
                'is_googlecalendar_sync' => $request->is_googlecalendar_sync ?? false,
            ]);

            // Attach members to the meeting
            if (!empty($request->member_ids)) {
                $meeting->members()->attach($request->member_ids);
            }

            // Sync with Google Calendar if enabled
            if ($request->is_googlecalendar_sync) {
                $this->syncMeetingWithGoogleCalendar($meeting);
            }

            // Fire event for email notification
            if (!config('app.is_demo', true)) {
                event(new ZoomMeetingCreated($meeting));
            }

            $message = $startUrl ? __('Zoom meeting created successfully!') : __('Meeting created successfully! Zoom API failed - please check your app activation and scopes in Zoom Marketplace.');
            
            return redirect()->route('zoom-meetings.index')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Error creating meeting: ') . $e->getMessage()]);
        }
    }

    public function getProjectMembers($projectId)
    {
        $this->authorizePermission('zoom_meeting_view_any');

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

    public function destroy(ZoomMeeting $zoomMeeting)
    {
        $this->authorizePermission('zoom_meeting_delete');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $zoomMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        try {
            // Delete Google Calendar event
            if ($zoomMeeting->google_calendar_event_id) {
                try {
                    $this->googleCalendarService->deleteEvent($zoomMeeting->google_calendar_event_id, auth()->id(), $user->current_workspace_id);
                } catch (\Exception $e) {
                    \Log::error('Failed to delete Google Calendar event', [
                        'meeting_id' => $zoomMeeting->id,
                        'event_id' => $zoomMeeting->google_calendar_event_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Only delete from Zoom if it's a real Zoom meeting (not local)
            if ($zoomMeeting->zoom_meeting_id && !str_starts_with($zoomMeeting->zoom_meeting_id, 'local_')) {
                $zoomResponse = $this->zoomService->deleteMeeting($zoomMeeting->zoom_meeting_id);
                
                if (!$zoomResponse['success']) {
                    return back()->withErrors(['error' => __('Failed to delete Zoom meeting: ') . $zoomResponse['error']]);
                }
            }

            $zoomMeeting->delete();

            return redirect()->route('zoom-meetings.index')
                ->with('success', __('Meeting deleted successfully!'));
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Error deleting meeting: ') . $e->getMessage()]);
        }
    }

    public function join(ZoomMeeting $zoomMeeting)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $zoomMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        if (!$zoomMeeting->join_url) {
            return back()->withErrors(['error' => __('Zoom credentials not configured. Please configure Zoom settings to enable video conferencing.')]);
        }

        return redirect()->away($zoomMeeting->join_url);
    }

    public function start(ZoomMeeting $zoomMeeting)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $zoomMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        if (!$zoomMeeting->start_url) {
            return back()->withErrors(['error' => __('Zoom credentials not configured. Please configure Zoom settings to enable video conferencing.')]);
        }

        $zoomMeeting->update(['status' => 'started']);

        return redirect()->away($zoomMeeting->start_url);
    }

    public function show(ZoomMeeting $zoomMeeting)
    {
        $this->authorizePermission('zoom_meeting_view');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $zoomMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        $zoomMeeting->load(['user', 'project', 'workspace', 'members']);

        // Transform avatar URLs
        $meetingData = $zoomMeeting->toArray();
        if ($zoomMeeting->user) {
            $meetingData['user']['avatar'] = check_file($zoomMeeting->user->avatar)
                ? get_file($zoomMeeting->user->avatar)
                : get_file('avatars/avatar.png');
        }
        if ($zoomMeeting->members) {
            $meetingData['members'] = $zoomMeeting->members->map(function ($member) {
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

        return Inertia::render('zoom-meetings/Show', [
            'meeting' => $meetingData,
            'projects' => $projects,
            'members' => $members,
            'permissions' => $this->getModuleCrudPermissions('zoom_meeting'),
            'googleCalendarEnabled' => $googleCalendarEnabled
        ]);
    }

    public function update(Request $request, ZoomMeeting $zoomMeeting)
    {
        $this->authorizePermission('zoom_meeting_update');

        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace || $zoomMeeting->workspace_id !== $workspace->id) {
            abort(403, 'Meeting not found in current workspace.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:15|max:480',
            'timezone' => 'required|string',
            'password' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'status' => 'nullable|in:scheduled,started,ended,cancelled',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
            'is_googlecalendar_sync' => 'nullable|boolean',
        ]);

        $startTime = Carbon::parse($request->start_time);
        $endTime = $startTime->copy()->addMinutes($request->duration);
        
        $meetingData = [
            'title' => $request->title,
            'description' => $request->description,
            'start_time' => $startTime->toISOString(),
            'duration' => $request->duration,
            'timezone' => $request->timezone,
            'password' => $request->password,
        ];

        try {
            // Update meeting in Zoom (only for real Zoom meetings, not local)
            if ($zoomMeeting->zoom_meeting_id && !str_starts_with($zoomMeeting->zoom_meeting_id, 'local_')) {
                $zoomResponse = $this->zoomService->updateMeeting($zoomMeeting->zoom_meeting_id, $meetingData);

                if (!$zoomResponse['success']) {
                    return back()->withErrors(['error' => __('Failed to update Zoom meeting: ') . $zoomResponse['error']]);
                }
            }

            // Update in database
            $zoomMeeting->update([
                'title' => $request->title,
                'description' => $request->description,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $request->timezone,
                'duration' => $request->duration,
                'password' => $request->password,
                'project_id' => $request->project_id,
                'status' => $request->status ?? $zoomMeeting->status,
            ]);

            // Update members
            if ($request->has('member_ids')) {
                $zoomMeeting->members()->sync($request->member_ids ?? []);
            }

            // Sync with Google Calendar if enabled
            if ($validated['is_googlecalendar_sync'] ?? false) {
                $this->syncMeetingWithGoogleCalendar($zoomMeeting);
            } elseif ($zoomMeeting->google_calendar_event_id && !($validated['is_googlecalendar_sync'] ?? false)) {
                // Remove from Google Calendar if sync was disabled
                $this->googleCalendarService->deleteEvent($zoomMeeting->google_calendar_event_id, auth()->id());
                $zoomMeeting->update(['google_calendar_event_id' => null]);
            }

            return redirect()->route('zoom-meetings.index')
                ->with('success', __('Zoom meeting updated successfully!'));
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Error updating meeting: ') . $e->getMessage()]);
        }
    }

    public function calendar()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return response()->json([]);
        }

        $meetings = ZoomMeeting::with(['project', 'user'])
            ->forWorkspace($workspace->id)
            ->get()
            ->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start' => $meeting->start_time->toISOString(),
                    'end' => $meeting->end_time->toISOString(),
                    'backgroundColor' => '#1976d2',
                    'borderColor' => '#1976d2',
                    'extendedProps' => [
                        'type' => 'zoom_meeting',
                        'status' => $meeting->status,
                        'join_url' => $meeting->join_url,
                        'start_url' => $meeting->start_url,
                        'project' => $meeting->project?->title,
                        'description' => $meeting->description,
                        'duration' => $meeting->duration,
                        'timezone' => $meeting->timezone,
                    ]
                ];
            });

        return response()->json($meetings);
    }

    /**
     * Sync meeting with Google Calendar
     */
    private function syncMeetingWithGoogleCalendar(ZoomMeeting $meeting)
    {
        try {
            $user = auth()->user();
            $workspaceId = $user->current_workspace_id;
            
            // Check if Google Calendar is enabled and configured
            $googleCalendarEnabled = getSetting('is_googlecalendar_sync', '0', $user->id, $workspaceId);
            
            if ($googleCalendarEnabled !== '1') {
                return;
            }
            
            if ($meeting->google_calendar_event_id) {
                // Update existing event
                $this->googleCalendarService->updateMeetingEvent($meeting->google_calendar_event_id, $meeting, $user->id, $workspaceId);
            } else {
                // Create new event
                $eventId = $this->googleCalendarService->createMeetingEvent($meeting, $user->id, $workspaceId);
                if ($eventId) {
                    $meeting->update(['google_calendar_event_id' => $eventId]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to sync meeting with Google Calendar', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}