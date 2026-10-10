<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file given to the AI Assistant, with what Sundal read from it.
 *
 * structure, spreadsheets: ['sheets' => [['name', 'header_row', 'headers' => [...],
 *   'rows' => [[...], ...], 'total_rows', 'truncated']]]
 * structure, documents: ['sections' => [['title', 'start', 'length']], 'pages', 'chars']
 * Both: 'tokens' (a rough estimate of what reading all of it costs).
 */
class AiAttachment extends Model
{
    use BelongsToWorkspace;

    public const READY = 'ready';
    public const FAILED = 'failed';
    /** A scanned PDF: no text layer; a vision-capable model can read it. */
    public const NEEDS_OCR = 'needs_ocr';

    public const SPREADSHEET = 'spreadsheet';
    public const DOCUMENT = 'document';
    public const TEXT = 'text';

    protected $fillable = [
        'workspace_id', 'user_id', 'ai_conversation_id', 'ai_message_id', 'source', 'original_name', 'extension',
        'mime', 'size', 'disk', 'path', 'kind', 'status', 'error', 'extracted_text', 'structure',
    ];

    protected $casts = [
        'structure' => 'array',
        'size' => 'integer',
    ];

    protected $hidden = ['extracted_text', 'structure', 'disk', 'path'];

    protected static function booted(): void
    {
        // The stored file goes with the row.
        static::deleting(function (AiAttachment $attachment) {
            try {
                Storage::disk($attachment->disk)->delete($attachment->path);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)->where('workspace_id', $user->current_workspace_id);
    }

    public function isReady(): bool
    {
        return $this->status === self::READY;
    }

    /** @return array<int, array<string, mixed>> */
    public function sheets(): array
    {
        return $this->structure['sheets'] ?? [];
    }

    /** @return array<int, array{title: string, start: int, length: int}> */
    public function sections(): array
    {
        return $this->structure['sections'] ?? [];
    }

    public function tokenEstimate(): int
    {
        return (int) ($this->structure['tokens'] ?? 0);
    }

    /** "QA_bugs.xlsx · sheet Bugs, 42 rows" for the chip and the model. */
    public function summary(): string
    {
        if ($this->status === self::FAILED) {
            return (string) $this->error;
        }
        if ($this->status === self::NEEDS_OCR) {
            return __('Scanned PDF (:pages pages), no text layer', ['pages' => $this->structure['pages'] ?? '?']);
        }

        return match ($this->kind) {
            self::SPREADSHEET => collect($this->sheets())
                ->map(fn ($sheet) => $sheet['name'] . ': ' . trans_choice(':count row|:count rows', $sheet['total_rows']))
                ->implode(', '),
            default => trim(implode(' · ', array_filter([
                isset($this->structure['pages']) ? trans_choice(':count page|:count pages', $this->structure['pages']) : null,
                trans_choice(':count section|:count sections', count($this->sections())),
            ]))),
        };
    }

    /** What the page shows for a file chip. */
    public function toChip(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'size' => $this->size,
            'kind' => $this->kind,
            'status' => $this->status,
            'source' => $this->source,
            'summary' => $this->summary(),
            'tokens' => $this->tokenEstimate(),
        ];
    }
}
