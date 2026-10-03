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
        'gateway_response',
        'receipt_path',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

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

    /** Payments that count towards what an invoice has been paid. */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    protected static function booted()
    {
        // A payment only counts once it's confirmed: either verified with the
        // payment provider on the server (callers set status = completed), or
        // approved by the invoice owner. Anything else - including a payment
        // reported only by the payer's browser - waits as pending. The DB
        // column defaults to 'completed', so the default is set here.
        static::creating(function ($payment) {
            if (empty($payment->status)) {
                $payment->status = self::STATUS_PENDING;
            }
        });

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