<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $fillable = [
        'name',
        'type',
        'minimum_spend',
        'maximum_spend',
        'discount_amount',
        'use_limit_per_coupon',
        'use_limit_per_user',
        'expiry_date',
        'code',
        'code_type',
        'status',
        'created_by'
    ];

    protected $casts = [
        'minimum_spend' => 'decimal:2',
        'maximum_spend' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'expiry_date' => 'date',
        'status' => 'boolean'
    ];

    protected $appends = ['is_expired'];

    /** A coupon is valid through the end of its expiry date. */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(now()->startOfDay());
    }

    public function scopeNotExpired($query)
    {
        return $query->where(fn ($q) => $q->whereNull('expiry_date')
            ->orWhereDate('expiry_date', '>=', now()->toDateString()));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString());
    }

    /** Enabled and not past its expiry date - the only coupons that may be applied. */
    public function scopeUsable($query)
    {
        return $query->where('status', 1)->notExpired();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
