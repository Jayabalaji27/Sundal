<?php

namespace App\Http\Controllers;

use App\Models\TimesheetEntry;
use App\Models\Timesheet;
use App\Models\Project;
use App\Models\Task;

use Illuminate\Http\Request;

class TimesheetEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = TimesheetEntry::with(['timesheet', 'project', 'task', 'user'])
            ->whereHas('timesheet', function($q) use ($user) {
                $q->where('workspace_id', $user->current_workspace_id);
            });

        if (!$user->can('manage-any-timesheets')) {
            $query->where('user_id', $user->id);
        }

        if ($request->project_id) {
            $query->forProject($request->project_id);
        }

        if ($request->date) {
            $query->forDate($request->date);
        }

        $entries = $query->latest()->paginate(50);

        return response()->json(['entries' => $entries]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'task_id' => 'nullable|exists:tasks,id',
                'date' => 'required|date',
                'start_time' => 'nullable',
                'end_time' => 'nullable',
                'hours' => 'required|numeric|min:' . TimesheetEntry::MIN_HOURS . '|max:' . TimesheetEntry::MAX_HOURS_PER_DAY,
                'description' => 'nullable|string',
                'is_billable' => 'boolean'
            ]);

            $this->ensureValidHours($validated, auth()->id());

            // Shared with the AI assistant. The timesheet is the one for the week the
            // entry's date falls in, never one sent by the client.
            app(\App\Actions\Timesheets\LogTime::class)->handle(auth()->user(), $validated);

            return redirect()->back()->with('success', __('Time entry created successfully.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\App\Actions\ActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to create time entry. Please try again.'));
        }
    }

    public function update(Request $request, TimesheetEntry $timesheetEntry)
    {
        try {
            // Check if user can update this entry
            $user = auth()->user();
            if ($timesheetEntry->user_id !== $user->id && !$user->can('manage-any-timesheets')) {
                return redirect()->back()->with('error', __('You are not authorized to update this time entry.'));
            }

            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'task_id' => 'nullable|exists:tasks,id',
                'date' => 'required|date',
                'start_time' => 'nullable',
                'end_time' => 'nullable',
                'hours' => 'required|numeric|min:' . TimesheetEntry::MIN_HOURS . '|max:' . TimesheetEntry::MAX_HOURS_PER_DAY,
                'description' => 'nullable|string',
                'is_billable' => 'boolean'
            ]);

            $this->ensureValidHours($validated, $timesheetEntry->user_id, $timesheetEntry->id);

            // Shared with the AI assistant: a new date can move it to another week's timesheet.
            app(\App\Actions\Timesheets\UpdateTimeEntry::class)->handle($user, $timesheetEntry, $validated);

            return redirect()->back()->with('success', __('Time entry updated successfully.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\App\Actions\ActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update time entry. Please try again.'));
        }
    }

    public function destroy(TimesheetEntry $timesheetEntry)
    {
        try {
            // Check if user can delete this entry
            $user = auth()->user();
            if ($timesheetEntry->user_id !== $user->id && !$user->can('manage-any-timesheets')) {
                return redirect()->back()->with('error', __('You are not authorized to delete this time entry.'));
            }

            app(\App\Actions\Timesheets\DeleteTimeEntry::class)->handle($user, $timesheetEntry);

            return redirect()->back()->with('success', __('Time entry deleted successfully.'));
        } catch (\App\Actions\ActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete time entry. Please try again.'));
        }
    }

    public function bulkUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'entry_ids' => 'required|array',
                'entry_ids.*' => 'exists:timesheet_entries,id',
                'is_billable' => 'boolean'
            ]);

            $user = auth()->user();
            $entries = TimesheetEntry::whereIn('id', $validated['entry_ids'])->get();
            
            if ($entries->isEmpty()) {
                return redirect()->back()->with('error', __('No entries found to update.'));
            }

            // Check authorization for bulk update
            if (!$user->can('manage-any-timesheets')) {
                $unauthorizedEntries = $entries->where('user_id', '!=', $user->id);
                if ($unauthorizedEntries->count() > 0) {
                    return redirect()->back()->with('error', __('You are not authorized to update some of the selected entries.'));
                }
            }

            if ($entries->contains(fn ($entry) => $entry->timesheet?->isLocked())) {
                return redirect()->back()->with('error', __('Submitted or approved timesheets cannot be edited or deleted.'));
            }

            $updatedCount = TimesheetEntry::whereIn('id', $validated['entry_ids'])
                ->update(['is_billable' => $validated['is_billable']]);

            $billableStatus = $validated['is_billable'] ? 'billable' : 'non-billable';
            return redirect()->back()->with('success', __(':count time entries marked as :status.', ['count' => $updatedCount, 'status' => $billableStatus]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update entries. Please try again.'));
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'entry_ids' => 'required|array',
                'entry_ids.*' => 'exists:timesheet_entries,id'
            ]);

            $user = auth()->user();
            $entries = TimesheetEntry::whereIn('id', $validated['entry_ids'])->get();
            
            if ($entries->isEmpty()) {
                return redirect()->back()->with('error', __('No entries found to delete.'));
            }

            // Check authorization for each entry
            if (!$user->can('manage-any-timesheets')) {
                $unauthorizedEntries = $entries->where('user_id', '!=', $user->id);
                if ($unauthorizedEntries->count() > 0) {
                    return redirect()->back()->with('error', __('You are not authorized to delete some of the selected entries.'));
                }
            }

            $timesheets = $entries->pluck('timesheet')->unique();
            if ($timesheets->contains(fn ($timesheet) => $timesheet?->isLocked())) {
                return redirect()->back()->with('error', __('Submitted or approved timesheets cannot be edited or deleted.'));
            }

            $deletedCount = $entries->count();

            TimesheetEntry::whereIn('id', $validated['entry_ids'])->delete();

            // Recalculate totals for affected timesheets
            foreach ($timesheets as $timesheet) {
                if ($timesheet) {
                    $timesheet->calculateTotals();
                }
            }

            return redirect()->back()->with('success', __(':count time entries deleted successfully.', ['count' => $deletedCount]));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete entries. Please try again.'));
        }
    }

    /**
     * End time must be after start time (when both are given), and the user's total
     * for the day can't go over 24h.
     */
    private function ensureValidHours(array $validated, int $userId, ?int $ignoreEntryId = null): void
    {
        if ($problem = \App\Actions\Timesheets\LogTime::hoursProblem($validated, $userId, $ignoreEntryId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([$problem[0] => $problem[1]]);
        }
    }
}
