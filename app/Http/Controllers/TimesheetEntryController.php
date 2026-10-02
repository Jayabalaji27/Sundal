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

            // The timesheet is derived from the entry's own date, not trusted from the
            // client — Weekly/Monthly/Daily view previously passed whichever timesheet
            // happened to be "first" for the user/workspace regardless of which week the
            // entry's date actually fell in, so entries could silently land on the wrong
            // week's timesheet (or even a different user-visible page's).
            $user = auth()->user();
            $workspace = $user->currentWorkspace;
            $entryDate = \Carbon\Carbon::parse($validated['date']);

            $timesheet = Timesheet::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'workspace_id' => $workspace->id,
                    'start_date' => $entryDate->copy()->startOfWeek()->toDateString(),
                    'end_date' => $entryDate->copy()->endOfWeek()->toDateString(),
                ],
                ['status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0]
            );

            $entry = TimesheetEntry::create([
                ...$validated,
                'timesheet_id' => $timesheet->id,
                'user_id' => $user->id,
                'hourly_rate' => 0,
                'is_billable' => $validated['is_billable'] ?? true
            ]);

            $timesheet->calculateTotals();

            return redirect()->back()->with('success', __('Time entry created successfully.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
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

            $oldTimesheet = $timesheetEntry->timesheet;

            $entryDate = \Carbon\Carbon::parse($validated['date']);
            $newTimesheet = Timesheet::firstOrCreate(
                [
                    'user_id' => $timesheetEntry->user_id,
                    'workspace_id' => $oldTimesheet->workspace_id,
                    'start_date' => $entryDate->copy()->startOfWeek()->toDateString(),
                    'end_date' => $entryDate->copy()->endOfWeek()->toDateString(),
                ],
                ['status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0]
            );

            $timesheetEntry->update([...$validated, 'timesheet_id' => $newTimesheet->id]);

            // Editing the date can move an entry into a different week's timesheet —
            // recalculate both so neither is left with a stale total.
            $newTimesheet->calculateTotals();
            if ($oldTimesheet && $oldTimesheet->id !== $newTimesheet->id) {
                $oldTimesheet->calculateTotals();
            }

            return redirect()->back()->with('success', __('Time entry updated successfully.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
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

            $timesheet = $timesheetEntry->timesheet;
            $timesheetEntry->delete();
            if ($timesheet) {
                $timesheet->calculateTotals();
            }

            return redirect()->back()->with('success', __('Time entry deleted successfully.'));
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
        if (!empty($validated['start_time']) && !empty($validated['end_time'])
            && \Carbon\Carbon::parse($validated['end_time'])->lte(\Carbon\Carbon::parse($validated['start_time']))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'end_time' => __('The end time must be after the start time.'),
            ]);
        }

        $alreadyLogged = TimesheetEntry::hoursLoggedOn($userId, $validated['date'], array_filter([$ignoreEntryId]));
        if ($alreadyLogged + (float) $validated['hours'] > TimesheetEntry::MAX_HOURS_PER_DAY) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'hours' => __('This would bring your total for :date to :total hours. A day can have at most 24 hours (:logged already logged).', [
                    'date' => \Carbon\Carbon::parse($validated['date'])->toDateString(),
                    'total' => round($alreadyLogged + (float) $validated['hours'], 2),
                    'logged' => round($alreadyLogged, 2),
                ]),
            ]);
        }
    }
}
