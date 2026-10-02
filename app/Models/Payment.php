<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'transaction_id',
        'payment_date',
        'created_by',
        'workspace_id',
        'status',
        'gateway_response'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'gateway_response' => 'array'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->updatePaymentStatus();
            }
        });

        static::updated(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->updatePaymentStatus();
            }
        });

        static::deleted(function ($payment) {
            if ($payment->invoice) {
                $payment->invoice->updatePaymentStatus();
            }
        });
    }

    public function getPaymentMethodDisplayAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'check' => 'Check',
            'credit_card' => 'Credit Card',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online Payment',
            'bank' => 'Bank Transfer',
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'razorpay' => 'Razorpay',
            default => ucfirst($this->payment_method)
        };
    }
}