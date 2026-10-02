<?php

namespace App\Console\Commands;

use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RepairTimesheetWeeks extends Command
{
    protected $signature = 'timesheets:repair-weeks {--dry-run : Show what would change without saving}';

    protected $description = 'Re-buckets timesheet entries onto the Monday-Sunday week timesheet that actually contains their date, and removes any timesheet left with zero entries as a result. Fixes entries misfiled by the pre-fix Weekly/Monthly/Daily "Add Entry" flow, which attached new entries to an arbitrary existing timesheet instead of the correct week.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $sourceTimesheetIds = [];
        $destTimesheetIds = [];

        TimesheetEntry::with('timesheet')->chunkById(200, function ($entries) use (&$moved, &$sourceTimesheetIds, &$destTimesheetIds, $dryRun) {
            foreach ($entries as $entry) {
                $timesheet = $entry->timesheet;
                if (!$timesheet) {
                    continue;
                }

                $entryDate = Carbon::parse($entry->date);
                $correctStart = $entryDate->copy()->startOfWeek()->toDateString();
                $correctEnd = $entryDate->copy()->endOfWeek()->toDateString();

                $currentStart = Carbon::parse($timesheet->start_date)->toDateString();
                $currentEnd = Carbon::parse($timesheet->end_date)->toDateString();

                if ($currentStart === $correctStart && $currentEnd === $correctEnd) {
                    continue; // already correctly bucketed
                }

                $this->line("Entry #{$entry->id} (date {$entryDate->toDateString()}) on timesheet #{$timesheet->id} ({$currentStart} to {$currentEnd}) -> should be {$correctStart} to {$correctEnd}");

                if (!$dryRun) {
                    $correctTimesheet = Timesheet::firstOrCreate(
                        [
                            'user_id' => $timesheet->user_id,
                            'workspace_id' => $timesheet->workspace_id,
                            'start_date' => $correctStart,
                            'end_date' => $correctEnd,
                        ],
                        ['status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0]
                    );

                    $entry->update(['timesheet_id' => $correctTimesheet->id]);
                    $sourceTimesheetIds[] = $timesheet->id;
                    $destTimesheetIds[] = $correctTimesheet->id;
                }

                $moved++;
            }
        });

        if (!$dryRun) {
            Timesheet::whereIn('id', array_unique(array_merge($sourceTimesheetIds, $destTimesheetIds)))
                ->get()
                ->each
                ->calculateTotals();

            // Only remove timesheets entries were actually moved OUT of during this run
            // (not a blanket sweep of every empty timesheet in the system) — a timesheet
            // a user deliberately created via "+ New Timesheet" and hasn't logged against
            // yet is left alone.
            $deleted = Timesheet::whereIn('id', array_unique($sourceTimesheetIds))
                ->doesntHave('entries')
                ->delete();

            $this->info("Deleted {$deleted} now-empty timesheet(s) that entries were moved out of.");
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Repaired {$moved} misfiled entr" . ($moved === 1 ? 'y' : 'ies') . '.');

        return self::SUCCESS;
    }
}
