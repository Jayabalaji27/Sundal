<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanRequest extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'duration',
        'status',
        'message',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejected_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Requests don't store prices (no coupon applies to a manual request), so the
    // amounts shown in the list come from the plan's price for the requested cycle.
    protected $appends = ['subtotal', 'total'];

    public function getSubtotalAttribute(): ?float
    {
        return $this->plan ? (float) $this->plan->getPriceForCycle($this->duration === 'yearly' ? 'yearly' : 'monthly') : null;
    }

    public function getTotalAttribute(): ?float
    {
        return $this->subtotal;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
