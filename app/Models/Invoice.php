<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToWorkspace;

class Invoice extends Model
{
    use HasFactory, LogsActivity, BelongsToWorkspace;

    protected $fillable = [
        'invoice_number',
        'project_id',
        'workspace_id',
        'client_id',
        'created_by',
        'title',
        'description',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'paid_amount',
        'sent_at',
        'viewed_at',
        'paid_at',
        'payment_method',
        'payment_reference',
        'payment_details',
        'client_details',
        'notes',
        'terms',
        'payment_token'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_details' => 'array',
        'client_details' => 'array',
    ];

    protected $appends = [
        'formatted_total',
        'balance_due',
        'remaining_amount',
        'is_overdue',
        'days_overdue',
        'status_color',
        'payment_url',
        'selected_taxes'
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Scopes
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Statuses an invoice can be overdue in: it has been issued to the client and
     * still has a balance. Drafts were never sent, so they're never overdue.
     */
    public const OVERDUE_ELIGIBLE_STATUSES = ['sent', 'viewed', 'partial_paid', 'overdue'];

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', self::OVERDUE_ELIGIBLE_STATUSES)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->whereColumn('total_amount', '>', 'paid_amount');
    }

    public function scopeNotOverdue($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('due_date')
                ->orWhereDate('due_date', '>=', now()->toDateString())
                ->orWhereColumn('total_amount', '<=', 'paid_amount');
        });
    }

    /**
     * Status tab buckets used by the invoices list. "overdue" is its own bucket,
     * so sent/partial_paid exclude overdue invoices and the buckets add up to "all"
     * (cancelled invoices aside).
     */
    public function scopeInStatusBucket($query, string $bucket)
    {
        return match ($bucket) {
            'overdue' => $query->overdue(),
            'sent' => $query->whereIn('status', ['sent', 'viewed', 'overdue'])->notOverdue(),
            'partial_paid' => $query->where('status', 'partial_paid')->notOverdue(),
            default => $query->where('status', $bucket),
        };
    }

    /**
     * Invoices a workspace role may see - shared by the invoices list and the
     * dashboard widget so the two can't drift apart.
     */
    public function scopeVisibleToRole($query, User $user, ?string $role)
    {
        if (in_array($role, ['manager', 'member'])) {
            $onMyProjects = function ($projQ) use ($user) {
                $projQ->where(function ($projectQuery) use ($user) {
                    $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id);
                    })->orWhere('created_by', $user->id);
                });
            };

            return $query->whereHas('project', $onMyProjects)
                ->when($role === 'member', fn ($q) => $q->where('status', '!=', 'draft'));
        }

        if ($role === 'client') {
            // Clients only see sent invoices addressed to them
            return $query->where('client_id', $user->id)->where('status', '!=', 'draft');
        }

        // Owners see all invoices
        return $query;
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['sent', 'viewed']);
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2);
    }

    public function getBalanceDueAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getRemainingAmountAttribute()
    {
        $totalPaid = $this->payments()->completed()->sum('amount');
        return max(0, $this->total_amount - $totalPaid);
    }

    public function getDueAmountAttribute()
    {
        return $this->remaining_amount;
    }

    public function getIsOverdueAttribute()
    {
        return $this->due_date
            && in_array($this->status, self::OVERDUE_ELIGIBLE_STATUSES)
            && $this->due_date->lt(now()->startOfDay())
            && (float) $this->total_amount > (float) $this->paid_amount;
    }

    public function getDaysOverdueAttribute()
    {
        if (!$this->is_overdue) {
            return 0;
        }
        return (int) $this->due_date->diffInDays(now());
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'viewed' => 'yellow',
            'paid' => 'green',
            'partial_paid' => 'orange',
            'overdue' => 'red',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'sent' => 'Sent',
            'viewed' => 'Viewed',
            'paid' => 'Paid',
            'partial_paid' => 'Partially Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    // Methods
    public function generateInvoiceNumber()
    {
        $prefix = 'INV-' . date('Y') . '-';
        $lastInvoice = static::where('invoice_number', 'like', $prefix . '%')
                           ->orderBy('invoice_number', 'desc')
                           ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('amount');

        // tax_rate is an array of applied taxes ([{id, name, rate}, ...] — see
        // getTaxRateAttribute()/setTaxRateAttribute()), not a single percentage.
        $taxes = is_array($this->tax_rate) ? $this->tax_rate : [];
        $this->tax_amount = collect($taxes)->sum(function ($tax) {
            return ($this->subtotal * ($tax['rate'] ?? 0)) / 100;
        });

        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function markAsViewed()
    {
        if ($this->status === 'sent') {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now()
            ]);
        }
    }

    public function markAsPaid($amount = null, $paymentMethod = null, $paymentReference = null, $paymentDetails = null)
    {
        $paidAmount = $amount ?? $this->total_amount;
        $this->update([
            'status' => 'paid',
            'paid_amount' => $paidAmount,
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'payment_details' => $paymentDetails
        ]);
    }

    /**
     * Record a payment against this invoice. Pass $verified = true only when the
     * payment was confirmed with the payment provider on the server; otherwise
     * it's recorded as pending until the invoice owner approves it.
     */
    public function createPaymentRecord($amount, $paymentMethod, $transactionId, bool $verified = false)
    {
        $existingPayment = Payment::where('invoice_id', $this->id)
            ->where('transaction_id', $transactionId)
            ->first();

        if (!$existingPayment) {
            $payment = Payment::create([
                'invoice_id' => $this->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'payment_date' => now(),
                'created_by' => $this->created_by,
                'workspace_id' => $this->workspace_id,
                'status' => $verified ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING,
            ]);

            $this->updatePaymentStatus();
            return true;
        }
        return false;
    }

    public function updatePaymentStatus()
    {
        // Pending payments (unapproved bank transfers, unverified gateway
        // reports) don't count until they're confirmed.
        $totalPaid = $this->payments()->completed()->sum('amount');
        $oldStatus = $this->status;
        
        if ($totalPaid >= $this->total_amount) {
            $this->update(['status' => 'paid', 'paid_amount' => $totalPaid, 'paid_at' => now()]);
        } elseif ($totalPaid > 0) {
            $this->update(['status' => 'partial_paid', 'paid_amount' => $totalPaid]);
        } else {
            // Check if overdue (drafts were never issued, so they can't be overdue)
            if ($this->due_date < now() && in_array($this->status, ['sent', 'viewed'])) {
                $this->update(['status' => 'overdue']);
            }
        }
        
        // Log status change
        if ($oldStatus !== $this->status) {
            \Log::info('Invoice status updated', [
                'invoice_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $this->status,
                'total_paid' => $totalPaid,
                'total_amount' => $this->total_amount
            ]);
        }
    }

    public function getPaymentUrlAttribute()
    {
        if (!$this->payment_token) {
            $this->payment_token = \Str::random(32);
            $this->save();
        }
        return route('invoices.payment', $this->payment_token);
    }

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }
            if (!$invoice->payment_token) {
                $invoice->payment_token = \Str::random(32);
            }
        });

        static::deleting(function ($invoice) {
            $invoice->items()->delete();
        });
    }

    protected function getActivityDescription(string $action): string
    {
        return match($action) {
            'created' => "Invoice '{$this->invoice_number}' was created for {$this->formatted_total}",
            'updated' => "Invoice '{$this->invoice_number}' was updated",
            'deleted' => "Invoice '{$this->invoice_number}' was deleted",
            default => parent::getActivityDescription($action)
        };
    }

    public function getTaxRateAttribute($value)
    {
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    public function setTaxRateAttribute($value)
    {
        $this->attributes['tax_rate'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Tax IDs from the applied tax_rate array, for pre-selecting checkboxes
     * when editing an existing invoice.
     */
    public function getSelectedTaxesAttribute(): array
    {
        return collect($this->tax_rate)->pluck('id')->filter()->values()->all();
    }
}