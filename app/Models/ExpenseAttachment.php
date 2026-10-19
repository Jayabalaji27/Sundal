<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class ExpenseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_expense_id',
        'media_item_id',
        'uploaded_by',
        'attachment_type'
    ];

    /** Stores an uploaded file in the media library and attaches it to the expense. */
    public static function storeUpload(ProjectExpense $expense, UploadedFile $file, string $type, int $userId): self
    {
        $mediaItem = MediaItem::create([
            'name' => $file->getClientOriginalName(),
            'file_name' => $file->store('expense-receipts', 'public'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        return static::create([
            'project_expense_id' => $expense->id,
            'media_item_id' => $mediaItem->id,
            'uploaded_by' => $userId,
            'attachment_type' => $type,
        ]);
    }

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