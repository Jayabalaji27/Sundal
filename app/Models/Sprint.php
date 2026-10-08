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
        'project_id','workspace_id','name','goal','start_date','end_date','status','created_by'
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class,'sprint_tasks')->withTimestamps();
    }

    // Burndown data: returns array of {date, remaining} points
    public function burndownData(): array
    {
        if (!$this->start_date || !$this->end_date) return [];

        $tasks = $this->tasks()->get();
        $total = $tasks->count();
        if ($total === 0) return [];

        $start = $this->start_date->copy();
        $end   = $this->end_date->copy();
        $days  = $start->diffInDays($end) + 1;
        $today = now()->startOfDay();

        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            if ($date->gt($today)) break;
            // Tasks "done" = task stage with name containing done/complete/closed
            $completed = $tasks->filter(function ($t) use ($date) {
                return $t->taskStage &&
                    (str_contains(strtolower($t->taskStage->name), 'done') ||
                     str_contains(strtolower($t->taskStage->name), 'complete') ||
                     str_contains(strtolower($t->taskStage->name), 'closed')) &&
                    $t->updated_at->lte($date->copy()->endOfDay());
            })->count();
            $points[] = [
                'date'      => $date->format('M d'),
                'remaining' => $total - $completed,
                'ideal'     => round($total - ($total * ($i / max($days - 1, 1))), 1),
            ];
        }
        return $points;
    }
}
