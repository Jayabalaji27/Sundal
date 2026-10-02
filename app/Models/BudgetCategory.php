<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_budget_id',
        'name',
        'allocated_amount',
        'color',
        'description',
        'sort_order'
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function projectBudget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function getTotalSpentAttribute()
    {
        return $this->expenses()->where('status', 'approved')->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->allocated_amount - $this->total_spent;
    }

    public function getUtilizationPercentageAttribute()
    {
        return $this->allocated_amount > 0 ? ($this->total_spent / $this->allocated_amount) * 100 : 0;
    }

    public function getIsOverBudgetAttribute()
    {
        return $this->total_spent > $this->allocated_amount;
    }
}