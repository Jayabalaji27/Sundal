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

    /**
     * Approved project expenses that count against this budget: those dated inside
     * the budget period (start_date..end_date, either bound optional). Expenses
     * aren't linked to a budget directly, so the period is what ties them to it.
     */
    public function spentExpenses()
    {
        return $this->expenses()
            ->where('status', 'approved')
            ->when($this->start_date, fn ($q) => $q->whereDate('expense_date', '>=', $this->start_date->toDateString()))
            ->when($this->end_date, fn ($q) => $q->whereDate('expense_date', '<=', $this->end_date->toDateString()));
    }

    public function getTotalSpentAttribute()
    {
        return $this->spentExpenses()->sum('amount');
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

    /**
     * Budgets the user may see in their current workspace: managers, clients and
     * members only those of projects visible to them (Project::scopeVisibleTo).
     * Shared by the budgets list and the budget detail page.
     */
    public function scopeVisibleTo($query, User $user)
    {
        $workspace = $user->currentWorkspace;
        if (!$workspace) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('project_budgets.workspace_id', $workspace->id);

        if (in_array($workspace->getMemberRole($user), ['manager', 'client', 'member'], true)) {
            $query->whereHas('project', fn ($q) => $q->visibleTo($user));
        }

        return $query;
    }
}
