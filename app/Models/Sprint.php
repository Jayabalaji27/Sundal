<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\BelongsToWorkspace;

class Sprint extends Model
{
    use BelongsToWorkspace, \App\Models\Concerns\RecordsCreator;

    protected $fillable = [
        'project_id','workspace_id','name','goal','start_date','end_date','status','created_by',
        'committed_tasks','committed_points','completed_tasks','completed_points',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'committed_tasks' => 'integer',
        'committed_points' => 'integer',
        'completed_tasks' => 'integer',
        'completed_points' => 'integer',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class,'sprint_tasks')->using(SprintTask::class)->withTimestamps();
    }

    /**
     * Burndown: per day from the start date up to today, the work remaining and
     * the ideal straight line. Counts story points when the sprint has any,
     * otherwise tasks. A task is done when it sits in a completed stage
     * (Task::isCompleted); its last update stands in for the completion date.
     *
     * @return array<int, array{date: string, remaining: float|int, ideal: float}>
     */
    public function burndownData(): array
    {
        if (!$this->start_date || !$this->end_date) return [];

        $tasks = $this->tasks()->with('taskStage:id,is_completed')->get();
        $usePoints = $tasks->sum('story_points') > 0;
        $weight = fn (Task $task) => $usePoints ? (int) $task->story_points : 1;
        $total = $tasks->sum($weight);
        if ($total === 0) return [];

        $start = $this->start_date->copy();
        $end   = $this->end_date->copy();
        $days  = (int) $start->diffInDays($end) + 1;
        $today = now()->startOfDay();

        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            if ($date->gt($today)) break;
            $completed = $tasks
                ->filter(fn (Task $task) => $task->isCompleted() && $task->updated_at->lte($date->copy()->endOfDay()))
                ->sum($weight);
            $points[] = [
                'date'      => $date->format('M d'),
                'remaining' => $total - $completed,
                'ideal'     => round($total - ($total * ($i / max($days - 1, 1))), 1),
            ];
        }
        return $points;
    }
}
