<?php

namespace App\Actions\Timesheets;

use App\Actions\ActionException;
use App\Models\Timesheet;
use App\Models\TimesheetEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logs time for the actor. The entry goes on the timesheet of the week its
 * date falls in (created as a draft when missing); submitted or approved
 * timesheets are locked. Shared by TimesheetEntryController and the AI assistant.
 *
 * $data: project_id, task_id?, date, start_time?, end_time?, hours,
 * description?, is_billable?
 */
class LogTime
{
    public function handle(User $actor, array $data): TimesheetEntry
    {
        if ($problem = self::hoursProblem($data, $actor->id)) {
            throw new ActionException($problem[1]);
        }

        return DB::transaction(function () use ($actor, $data) {
            $timesheet = self::weekTimesheet($actor->id, (int) $actor->current_workspace_id, $data['date']);
            if ($timesheet->isLocked()) {
                throw new ActionException(__('Submitted or approved timesheets cannot be edited or deleted.'));
            }

            $entry = TimesheetEntry::create([
                ...array_intersect_key($data, array_flip(['project_id', 'task_id', 'date', 'start_time', 'end_time', 'hours', 'description'])),
                'timesheet_id' => $timesheet->id,
                'user_id' => $actor->id,
                'hourly_rate' => 0,
                'is_billable' => $data['is_billable'] ?? true,
            ]);

            $timesheet->calculateTotals();

            return $entry;
        });
    }

    /** The user's timesheet for the week containing $date. */
    public static function weekTimesheet(int $userId, int $workspaceId, string $date): Timesheet
    {
        $day = Carbon::parse($date);

        return Timesheet::firstOrCreate(
            [
                'user_id' => $userId,
                'workspace_id' => $workspaceId,
                'start_date' => $day->copy()->startOfWeek()->toDateString(),
                'end_date' => $day->copy()->endOfWeek()->toDateString(),
            ],
            ['status' => 'draft', 'total_hours' => 0, 'billable_hours' => 0]
        );
    }

    /**
     * The screen's hour rules: 0.25–24 hours, the end after the start, and at
     * most 24 hours in one day across entries.
     *
     * @return array{0: string, 1: string}|null [field, message] when broken
     */
    public static function hoursProblem(array $data, int $userId, ?int $ignoreEntryId = null): ?array
    {
        $hours = (float) ($data['hours'] ?? 0);
        if ($hours < TimesheetEntry::MIN_HOURS || $hours > TimesheetEntry::MAX_HOURS_PER_DAY) {
            return ['hours', __('Hours must be between :min and :max.', ['min' => TimesheetEntry::MIN_HOURS, 'max' => TimesheetEntry::MAX_HOURS_PER_DAY])];
        }

        if (!empty($data['start_time']) && !empty($data['end_time'])
            && Carbon::parse($data['end_time'])->lte(Carbon::parse($data['start_time']))) {
            return ['end_time', __('The end time must be after the start time.')];
        }

        $alreadyLogged = TimesheetEntry::hoursLoggedOn($userId, $data['date'], array_filter([$ignoreEntryId]));
        if ($alreadyLogged + $hours > TimesheetEntry::MAX_HOURS_PER_DAY) {
            return ['hours', __('This would bring your total for :date to :total hours. A day can have at most 24 hours (:logged already logged).', [
                'date' => Carbon::parse($data['date'])->toDateString(),
                'total' => round($alreadyLogged + $hours, 2),
                'logged' => round($alreadyLogged, 2),
            ])];
        }

        return null;
    }
}
