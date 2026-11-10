<?php

namespace App\Actions\Sprints;

use App\Actions\ActionException;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Completes the active sprint. Shared by SprintController and the AI
 * assistant. Unfinished tasks (not in a completed stage) leave the sprint
 * for the backlog, a planned sprint of the project, or a new sprint; what
 * was committed and finished is frozen on the sprint.
 *
 * @param  string|Sprint  $moveTo  'backlog', 'new', or the planned sprint
 * @return array{0: int, 1: ?string} unfinished tasks moved, and the sprint they went to (null: backlog)
 */
class CompleteSprint
{
    public function __construct(private readonly AddTasksToSprint $addTasks) {}

    public function handle(User $actor, Sprint $sprint, string|Sprint $moveTo = 'backlog'): array
    {
        if ($sprint->status !== 'active') {
            throw new ActionException(__('Only the active sprint can be completed.'));
        }
        $target = $moveTo instanceof Sprint ? $moveTo : null;
        if ($target && ((int) $target->project_id !== (int) $sprint->project_id || $target->status !== 'planning')) {
            throw new ActionException(__('Choose a planned sprint of this project for the unfinished tasks.'));
        }

        return DB::transaction(function () use ($actor, $sprint, $moveTo, $target) {
            $all = $sprint->tasks()->with('taskStage:id,is_completed')->get();
            $finished = $all->filter(fn (Task $task) => $task->isCompleted());
            $unfinished = $all->reject(fn (Task $task) => $task->isCompleted())->values();

            if ($moveTo === 'new') {
                $target = Sprint::create([
                    'project_id' => $sprint->project_id,
                    'workspace_id' => $sprint->workspace_id,
                    'name' => self::nextName($sprint->project_id),
                    'status' => 'planning',
                    'created_by' => $actor->id,
                ]);
            }

            $sprint->tasks()->detach($unfinished->pluck('id'));
            if ($target && $unfinished->isNotEmpty()) {
                $this->addTasks->handle($actor, $target, $unfinished);
            }

            // Freeze what was committed and finished: the unfinished tasks leave the sprint now.
            $sprint->update([
                'status' => 'completed',
                'end_date' => $sprint->end_date ?? now()->toDateString(),
                'committed_tasks' => $all->count(),
                'committed_points' => (int) $all->sum('story_points'),
                'completed_tasks' => $finished->count(),
                'completed_points' => (int) $finished->sum('story_points'),
            ]);

            return [$unfinished->count(), $target?->name];
        });
    }

    /** "Sprint 4" for a project with three sprints. */
    public static function nextName(int $projectId): string
    {
        return 'Sprint ' . (Sprint::where('project_id', $projectId)->count() + 1);
    }

    /** The message both callers show. */
    public static function message(Sprint $sprint, int $count, ?string $targetName): string
    {
        return $count === 0
            ? __(':name completed.', ['name' => $sprint->name])
            : ($targetName
                ? trans_choice(':name completed. :count unfinished task moved to :target.|:name completed. :count unfinished tasks moved to :target.', $count, ['name' => $sprint->name, 'count' => $count, 'target' => $targetName])
                : trans_choice(':name completed. :count unfinished task moved to the backlog.|:name completed. :count unfinished tasks moved to the backlog.', $count, ['name' => $sprint->name, 'count' => $count]));
    }
}
