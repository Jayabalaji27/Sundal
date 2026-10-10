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

        // Shared with the AI assistant: a timer running in another workspace is stopped first.
        try {
            $entry = app(\App\Actions\Timer\StartTimer::class)->handle($user, $project, $validated['task_id'] ?? null, $validated['description'] ?? null);
        } catch (\App\Actions\ActionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json([
            'status' => 'started',
            'started_at' => now(),
            'entry_id' => $entry->id
        ]);
    }

    public function stop(Request $request)
    {
        $user = auth()->user();

        if (!$user->timer_active) {
            return response()->json(['error' => __('No active timer')], 400);
        }

        // Shared with the AI assistant.
        $stopped = app(\App\Actions\Timer\StopTimer::class)->handle($user);
        $hours = $stopped['hours'];
        $entryId = $stopped['entry_id'];
        $totalSeconds = $stopped['seconds'];

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

        // This is a background poll. Flash data lives for exactly one request,
        // so a poll landing between a form POST and the redirected page load
        // would consume the flash (e.g. a plan-limit error) and the user would
        // see nothing. Keep it for the next real page visit.
        if (! $request->inertia() && $request->hasSession()) {
            $request->session()->reflash();
        }

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

    private function forceStopTimer(User $user)
    {
        app(\App\Actions\Timer\StopTimer::class)->handle($user);
    }
}