<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\AiAccess;
use App\Services\Ai\Attachments\AttachmentContext;

/** Rows of an attached spreadsheet, a page at a time, keyed by column name. */
class GetSheetRows extends AiTool
{
    private const MAX_ROWS = 100;

    public function __construct(private readonly RecordResolver $resolver) {}

    public function name(): string
    {
        return 'get_sheet_rows';
    }

    public function description(): string
    {
        return 'Read rows of a spreadsheet the user attached (Excel or CSV), at most 100 at a time. Rows are numbered from 1 (the first row after the column names).';
    }

    public function permissions(): array
    {
        return [];
    }

    public function allowedFor(User $user): bool
    {
        return AiAccess::canUse($user);
    }

    public function parameters(): array
    {
        return [
            'attachment' => ['type' => 'string', 'description' => 'The file: its id (e.g. #12) or name. Defaults to the latest attached file.'],
            'sheet' => ['type' => 'string', 'description' => 'Sheet name. Defaults to the first sheet.'],
            'from' => ['type' => 'number', 'description' => 'First row to read, from 1. Defaults to 1.'],
            'count' => ['type' => 'number', 'description' => 'How many rows, at most 100. Defaults to 50.'],
        ];
    }

    public function run(array $args, User $user): array
    {
        $file = $this->resolver->attachment($user, (string) ($args['attachment'] ?? ''));
        $sheet = AttachmentContext::sheet($file, $args['sheet'] ?? null);

        $from = max(1, (int) ($args['from'] ?? 1));
        $count = min(self::MAX_ROWS, max(1, (int) ($args['count'] ?? 50)));
        $rows = array_slice($sheet['rows'], $from - 1, $count);

        return [
            'file' => $file->original_name,
            'sheet' => $sheet['name'],
            'columns' => $sheet['headers'],
            'rows' => array_map(fn ($row, $i) => [
                'row' => $from + $i,
                'sheet_row' => $row['n'],
                'cells' => array_combine($sheet['headers'], array_map(fn ($c) => AttachmentContext::defang((string) $c), $row['c'])),
            ], $rows, array_keys($rows)),
            'shown' => count($rows),
            'total_rows' => $sheet['total_rows'],
            'more' => $from - 1 + count($rows) < count($sheet['rows']),
        ];
    }
}
