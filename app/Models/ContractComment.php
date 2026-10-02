<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractComment extends BaseModel
{
    use HasFactory;

    protected $table = 'contracts_comments';

    protected $fillable = [
        'contract_id',
        'comment',
        'parent_id',
        'is_internal',
        'created_by',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ContractComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContractComment::class, 'parent_id');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeWithReplies($query)
    {
        return $query->with(['replies' => function ($q) {
            $q->with('creator')->orderBy('created_at');
        }]);
    }
}