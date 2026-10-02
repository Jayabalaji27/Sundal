<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

class ProjectBudget extends Model
{
    use HasFactory, LogsActivity, BelongsToWorkspace;

    protected $fillable = [
        'project_id',
        'workspace_id',
        'total_budget',
        'period_type',
        'start_date',
        'end_date',
        'description',
        'status',
        'created_by'
    ];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class)->orderBy('sort_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class, 'project_id', 'project_id');
    }

    protected static function booted()
    {
        static::deleting(function ($budget) {
            $budget->expenses()->delete();
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BudgetRevision::class)->latest();
    }

    public function getTotalSpentAttribute()
    {
        return $this->expenses()->where('status', 'approved')->sum('amount');
    }

    public function getRemainingBudgetAttribute()
    {
        return $this->total_budget - $this->total_spent;
    }

    public function getUtilizationPercentageAttribute()
    {
        return $this->total_budget > 0 ? ($this->total_spent / $this->total_budget) * 100 : 0;
    }

    protected function getActivityDescription(string $action): string
    {
        return match($action) {
            'created' => "Budget '{$this->description}' was created with amount {$this->total_budget}",
            'updated' => "Budget '{$this->description}' was updated",
            'deleted' => "Budget '{$this->description}' was deleted",
            default => parent::getActivityDescription($action)
        };
    }
}