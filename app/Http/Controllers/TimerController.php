<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\Workspace;

use Illuminate\Http\Request;
use Carbon\Carbon;

class TimerController extends Controller
{
    public function start(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string'
        ]);

        $user = auth()->user();

        // Validate that user has access to the project
        $project = Project::find($validated['project_id']);
        if (!$project || !$user->canAccessWorkspace($project->workspace)) {
            return response()->json(['error' => __('Access denied to this project')], 403);
        }

        // Check if timer is active in ANY workspace
        if ($user->timer_active) {
            $timerProject = Project::find($user->timer_project_id);
            if ($timerProject) {
                if ($timerProject->workspace_id != $user->current_workspace_id) {
                    // Timer is running in a different workspace - stop it first
                    $this->forceStopTimer($user);
                } else {
                    // Timer is already running in current workspace
                    return response()->json(['error' => __('Timer is already active')], 400);
                }
            } else {
                // Timer project not found - reset timer
                $this->forceStopTimer($user);
            }
        }

        try {
            // Create initial timesheet entry
            $entryId = $this->createInitialTimesheetEntry($user, $validated);

            $user->update([
                'timer_active' => true,
                'timer_project_id' => $validated['project_id'],
                'timer_task_id' => $validated['task_id'],
                'timer_started_at' => now(),
                'timer_description' => $validated['description'],
                'timer_elapsed_seconds' => 0,
                'timer_entry_id' => $entryId
            ]);

            return response()->json([
                'status' => 'started', 
                'started_at' => now(),
                'entry_id' => $entryId
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function stop(Request $request)
    {
        $user = auth()->user();

        if (!$user->timer_active) {
            return response()->json(['error' => __('No active timer')], 400);
        }

        $totalSeconds = $user->timer_elapsed_seconds;
        if ($user->timer_started_at) {
            $startTime = Carbon::parse($user->timer_started_at);
            $totalSeconds += $startTime->diffInSeconds(now());
        }
        $hours = round($totalSeconds / 3600, 2);

        // Update existing timesheet entry
        $this->updateTimesheetEntry($user, $hours);

        // Get the entry before resetting timer for response
        $entryId = $user->timer_entry_id;

        // Reset timer
        $user->update([
            'timer_active' => false,
            'timer_project_id' => null,
            'timer_task_id' => null,
            'timer_started_at' => null,
            'timer_description' => null,
            'timer_elapsed_seconds' => 0,
            'timer_entry_id' => null
        ]);

        return response()->json([
            'status' => 'stopped', 
            'hours' => $hours,
            'entry_id' => $entryId,
            'total_seconds' => $totalSeconds
        ]);
    }

    public function pause(Request $request)
    {
        $user = auth()->user();

        if (!$user->timer_active) {
            return response()->json(['error' => __('No active timer')], 400);
        }

        if (!$user->timer_started_at) {
            return response()->json(['error' => __('Timer is already paused')], 400);
        }

        $startTime = Carbon::parse($user->timer_started_at);
        $elapsedSeconds = $startTime->diffInRealSeconds(now()) + $user->timer_elapsed_seconds;

        $user->update([
            'timer_started_at' => null,
            'timer_elapsed_seconds' => $elapsedSeconds
        ]);

        return response()->json(['status' => 'paused', 'elapsed_seconds' => $elapsedSeconds]);
    }

    public function resume(Request $request)
    {
        $user = auth()->user();

        if (!$user->timer_active) {
            return response()->json(['error' => __('No timer to resume')], 400);
        }

        if ($user->timer_started_at) {
            return response()->json(['error' => __('Timer is already running')], 400);
        }

        $user->update(['timer_started_at' => now()]);

        return response()->json(['status' => 'resumed', 'started_at' => now()]);
    }

    public function status(Request $request)
    {
        $user = auth()->user();

        if (!$user->timer_active) {
            return $request->inertia() 
                ? back()->with('timer', ['active' => false])
                : response()->json(['active' => false]);
        }

        // Check if timer belongs to current workspace
        $project = Project::find($user->timer_project_id);
        if (!$project || $project->workspace_id != $user->current_workspace_id) {
            return $request->inertia()
                ? back()->with('timer', ['active' => false])
                : response()->json(['active' => false]);
        }

        $elapsedSeconds = $user->timer_elapsed_seconds;
        if ($user->timer_started_at) {
            $startTime = Carbon::parse($user->timer_started_at);
            $elapsedSeconds += $startTime->diffInRealSeconds(now());
        }

        // Get the current timer entry if it exists
        $timerEntry = null;
        if ($user->timer_entry_id) {
            $timerEntry = TimesheetEntry::with(['project', 'task', 'timesheet'])
                ->find($user->timer_entry_id);
        }

        return response()->json([
            'active' => true,
            'project_id' => $user->timer_project_id,
            'task_id' => $user->timer_task_id,
            'description' => $user->timer_description,
            'started_at' => $user->timer_started_at,
            'elapsed_seconds' => $elapsedSeconds,
            'is_paused' => !$user->timer_started_at,
            'entry_id' => $user->timer_entry_id,
            'timer_entry' => $timerEntry
        ]);
    }

    private function createInitialTimesheetEntry(User $user, array $validated)
    {
        $today = now()->toDateString();
        $startOfWeek = now()->startOfWeek()->toDateString();
        $endOfWeek = now()->endOfWeek()->toDateString();

        // Ensure workspace_id is available
        if (!$user->current_workspace_id) {
            throw new \Exception(__('User must have a current workspace to start timer'));
        }

        // Find or create timesheet for current week
        $timesheet = Timesheet::firstOrCreate([
            'user_id' => $user->id,
            'workspace_id' => $user->current_workspace_id,
            'start_date' => $startOfWeek,
            'end_date' => $endOfWeek
        ], [
            'status' => 'draft',
            'total_hours' => 0,
            'billable_hours' => 0
        ]);

        // Create entry with 0 hours initially - this will be updated when timer stops
        $entry = TimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'project_id' => $validated['project_id'],
            'task_id' => $validated['task_id'],
            'user_id' => $user->id,
            'date' => $today,
            'start_time' => now()->format('H:i:s'),
            'end_time' => null,
            'hours' => 0,
            'description' => $validated['description'] ?? '',
            'is_billable' => true,
            'hourly_rate' => 0
        ]);

        return $entry->id;
    }

    private function updateTimesheetEntry(User $user, float $hours)
    {
        if ($user->timer_entry_id) {
            $entry = TimesheetEntry::find($user->timer_entry_id);
            if ($entry) {
                $entry->update([
                    'end_time' => now()->format('H:i:s'),
                    'hours' => $hours
                ]);
                $entry->timesheet->calculateTotals();
            }
        }
    }

    private function forceStopTimer(User $user)
    {
        $totalSeconds = $user->timer_elapsed_seconds;
        if ($user->timer_started_at) {
            $startTime = Carbon::parse($user->timer_started_at);
            $totalSeconds += $startTime->diffInSeconds(now());
        }
        $hours = round($totalSeconds / 3600, 2);
        $this->updateTimesheetEntry($user, $hours);
        
        $user->update([
            'timer_active' => false,
            'timer_project_id' => null,
            'timer_task_id' => null,
            'timer_started_at' => null,
            'timer_description' => null,
            'timer_elapsed_seconds' => 0,
            'timer_entry_id' => null
        ]);
    }
}