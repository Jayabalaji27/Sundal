<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class RecurringExpense extends Model
{
    use HasFactory;

    protected $table = 'expense_recurring';

    protected $fillable = [
        'project_id',
        'budget_category_id',
        'created_by',
        'title',
        'description',
        'amount',
        'currency',
        'vendor',
        'frequency',
        'start_date',
        'end_date',
        'next_occurrence',
        'is_active'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_occurrence' => 'date',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budgetCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calculateNextOccurrence(): Carbon
    {
        $current = Carbon::parse($this->next_occurrence);
        
        return match($this->frequency) {
            'weekly' => $current->addWeek(),
            'monthly' => $current->addMonth(),
            'quarterly' => $current->addMonths(3),
            'yearly' => $current->addYear(),
            default => $current->addMonth(),
        };
    }

    public function updateNextOccurrence(): void
    {
        $this->update(['next_occurrence' => $this->calculateNextOccurrence()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where('next_occurrence', '<=', now()->toDateString());
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}