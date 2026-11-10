<?php

namespace App\Actions\Sprints;

use App\Actions\ActionException;
use App\Models\Sprint;
use Illuminate\Support\Carbon;

/**
 * Starts a planned sprint. Shared by SprintController and the AI assistant.
 * One sprint per project is active at a time. Without dates the sprint
 * starts today (or on its planned start) and runs two weeks.
 *
 * $data: goal?, start_date?, end_date? (validated)
 */
class StartSprint
{
    public function handle(Sprint $sprint, array $data = []): Sprint
    {
        if ($sprint->status !== 'planning') {
            throw new ActionException(__('Only a planned sprint can be started.'));
        }
        if ($active = $this->activeSprint($sprint)) {
            throw new ActionException(__('Complete ":name" before starting another sprint.', ['name' => $active->name]));
        }

        $start = Carbon::parse($data['start_date'] ?? $sprint->start_date ?? now())->startOfDay();
        $end = isset($data['end_date']) ? Carbon::parse($data['end_date']) : ($sprint->end_date ?? $start->copy()->addWeeks(2));
        if ($end->lt($start)) {
            throw new ActionException(__('The sprint must end on or after its start date.'));
        }

        $sprint->update([
            'status' => 'active',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'goal' => array_key_exists('goal', $data) ? $data['goal'] : $sprint->goal,
        ]);

        return $sprint;
    }

    public function activeSprint(Sprint $sprint): ?Sprint
    {
        return Sprint::where('project_id', $sprint->project_id)->where('status', 'active')->whereKeyNot($sprint->id)->first();
    }
}
