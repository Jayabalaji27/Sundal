<?php

namespace App\Services\Ai\Tools;

use App\Models\AiAttachment;
use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\Attachments\AttachmentContext;
use App\Services\Ai\Attachments\ScannedPdfReader;

/**
 * Read part of a file the user attached: a document's section, or a
 * spreadsheet's outline. A scanned PDF is first read by the AI model when
 * it can read PDFs (Phase 4).
 */
class ReadAttachment extends AiTool
{
    public function __construct(
        private readonly RecordResolver $resolver,
        private readonly ScannedPdfReader $scans,
    ) {}

    public function name(): string
    {
        return 'read_attachment';
    }

    public function description(): string
    {
        return 'Read a file the user attached (PDF, Word, text or spreadsheet). For a document, give a section number (from its outline) to read that section; without one you get the outline and section 1. For a spreadsheet you get its sheets and columns; use get_sheet_rows for rows.';
    }

    public function permissions(): array
    {
        return [];
    }

    /** Anyone who may use the assistant may read their own files. */
    public function allowedFor(User $user): bool
    {
        return AiAccess::canUse($user);
    }

    public function parameters(): array
    {
        return [
            'attachment' => ['type' => 'string', 'description' => 'The file: its id (e.g. #12) or name. Defaults to the latest attached file.'],
            'section' => ['type' => 'number', 'description' => 'Section number, from 1.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $file = $this->resolver->attachment($user, (string) ($args['attachment'] ?? ''));

        if ($file->status === AiAttachment::NEEDS_OCR) {
            $file = $this->scans->read($file, $user);
        }

        if ($file->kind === AiAttachment::SPREADSHEET) {
            return [
                'file' => $file->original_name,
                'id' => "#{$file->id}",
                'sheets' => collect($file->sheets())->map(fn ($sheet) => [
                    'name' => $sheet['name'],
                    'rows' => $sheet['total_rows'],
                    'columns' => $sheet['headers'],
                    'first_rows' => array_map(fn ($row) => array_combine($sheet['headers'], $row['c']), array_slice($sheet['rows'], 0, 5)),
                ])->all(),
            ];
        }

        $number = max(1, (int) ($args['section'] ?? 1));

        return [
            'file' => $file->original_name,
            'id' => "#{$file->id}",
            'outline' => $number === 1 ? array_map(fn ($s, $i) => ($i + 1) . '. ' . $s['title'], $file->sections(), array_keys($file->sections())) : null,
            ...AttachmentContext::section($file, $number),
        ];
    }
}
