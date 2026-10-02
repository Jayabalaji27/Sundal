<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_expense_id',
        'media_item_id',
        'uploaded_by',
        'attachment_type'
    ];

    public function projectExpense(): BelongsTo
    {
        return $this->belongsTo(ProjectExpense::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getIsReceiptAttribute()
    {
        return $this->attachment_type === 'receipt';
    }

    public function getIsInvoiceAttribute()
    {
        return $this->attachment_type === 'invoice';
    }

    public function scopeReceipts($query)
    {
        return $query->where('attachment_type', 'receipt');
    }

    public function scopeInvoices($query)
    {
        return $query->where('attachment_type', 'invoice');
    }
}