<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Concerns\BelongsToWorkspace;

class Contract extends BaseModel
{
    use HasFactory, BelongsToWorkspace;

    protected $fillable = [
        'contract_id',
        'subject',
        'description',
        'contract_type_id',
        'contract_value',
        'start_date',
        'end_date',
        'status',
        'client_id',
        'project_id',
        'assigned_users',
        'terms_conditions',
        'notes',
        'currency',
        'workspace_id',
        'created_by',
        'signed_at',
        'sent_at',
        'company_signature',
        'client_signature',
        'accepted_at',
        'declined_at',
    ];

    protected $casts = [
        'assigned_users' => 'array',
        'contract_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (empty($contract->contract_id)) {
                $contract->contract_id = static::generateContractId();
            }
        });

        static::deleting(function ($contract) {
            foreach ($contract->attachments as $attachment) {
                delete_file($attachment->files);
            }
        });
    }

    public static function generateContractId(): string
    {
        do {
            $id = 'CON-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('contract_id', $id)->exists());

        return $id;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContractNote::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ContractComment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class);
    }

    public function assignedUsers()
    {
        if (empty($this->assigned_users)) {
            return collect();
        }

        return User::whereIn('id', $this->assigned_users)->get();
    }

    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('end_date', '<=', now()->addDays($days))
                    ->where('end_date', '>=', now())
                    ->whereIn('status', ['sent', 'accept']);
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now())
                    ->whereIn('status', ['sent', 'accept']);
    }

    public function isExpired(): bool
    {
        return $this->end_date < now() && in_array($this->status, ['sent', 'accept']);
    }

    public function isExpiringSoon($days = 30): bool
    {
        return $this->end_date <= now()->addDays($days) 
               && $this->end_date >= now() 
               && in_array($this->status, ['sent', 'accept']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => '#ffc107',
            'sent' => '#007bff',
            'accept' => '#28a745',
            'decline' => '#dc3545',
            'signed' => '#28a745',
            'declined' => '#dc3545',
            'expired' => '#fd7e14',
            'cancelled' => '#6f42c1',
            default => '#ffc107'
        };
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return now()->diffInDays($this->end_date, false);
    }
}