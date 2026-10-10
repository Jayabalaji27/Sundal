<?php

namespace App\Services\Ai\Attachments;

use App\Models\AiAttachment;
use App\Services\Ai\Tools\ToolInputException;

/**
 * What the model is told about an attached file, and the parts it reads
 * later. Small documents go in whole; for the rest the model gets an
 * outline and reads sections or rows with read_attachment / get_sheet_rows.
 * Everything is wrapped in <attached_file> as data, never instructions.
 */
class AttachmentContext
{
    /** Documents up to this many characters are handed over whole. */
    private const INLINE_CHARS = 6000;

    private const PREVIEW_ROWS = 5;

    private const MAX_OUTLINE = 40;

    public static function describe(AiAttachment $file): string
    {
        $open = sprintf('<attached_file id="%d" name="%s" kind="%s">', $file->id, self::attr($file->original_name), $file->kind);

        $body = match (true) {
            $file->status === AiAttachment::NEEDS_OCR => __('A scanned PDF (:pages pages) with no text layer. read_attachment can have it read by the AI model, if the model supports PDFs.', ['pages' => $file->structure['pages'] ?? '?']),
            $file->kind === AiAttachment::SPREADSHEET => self::describeSheets($file),
            mb_strlen((string) $file->extracted_text) <= self::INLINE_CHARS => trim((string) $file->extracted_text),
            default => self::outline($file),
        };

        return "{$open}\n" . self::defang($body) . "\n</attached_file>";
    }

    /** A file cannot close the wrapper early and pose as the user. */
    public static function defang(string $text): string
    {
        return (string) preg_replace('/<(\/?)(attached_file)/i', '‹$1$2', $text);
    }

    private static function describeSheets(AiAttachment $file): string
    {
        $lines = [];
        foreach ($file->sheets() as $sheet) {
            $lines[] = __('Sheet ":name": :rows data rows (header on row :header).', ['name' => $sheet['name'], 'rows' => $sheet['total_rows'], 'header' => $sheet['header_row']]);
            $lines[] = __('Columns: :columns', ['columns' => implode(' | ', $sheet['headers'])]);
            $lines[] = __('First rows:');
            foreach (array_slice($sheet['rows'], 0, self::PREVIEW_ROWS) as $row) {
                $lines[] = implode(' | ', array_map(fn ($c) => mb_strimwidth((string) $c, 0, 80, '…'), $row['c']));
            }
            $lines[] = '';
        }
        $lines[] = __('Read more rows with get_sheet_rows. To create bugs or tasks from the rows, use import_bugs_from_sheet or import_tasks_from_sheet: Sundal reads every row itself.');

        return trim(implode("\n", $lines));
    }

    private static function outline(AiAttachment $file): string
    {
        $sections = $file->sections();
        $lines = [__(':count characters in :sections sections:', ['count' => mb_strlen((string) $file->extracted_text), 'sections' => count($sections)])];
        foreach (array_slice($sections, 0, self::MAX_OUTLINE) as $i => $section) {
            $lines[] = ($i + 1) . '. ' . $section['title'];
        }
        if (count($sections) > self::MAX_OUTLINE) {
            $lines[] = '…';
        }
        $lines[] = __('Read a section with read_attachment (attachment ":id", section N). To turn the document into tasks, use plan_tasks_from_document.', ['id' => "#{$file->id}"]);

        return implode("\n", $lines);
    }

    /** One section of a document, numbered from 1. */
    public static function section(AiAttachment $file, int $number): array
    {
        $sections = $file->sections();
        $section = $sections[$number - 1] ?? throw new ToolInputException(__('There is no section :n; the file has :count.', ['n' => $number, 'count' => count($sections)]));

        return [
            'section' => $number,
            'of' => count($sections),
            'title' => $section['title'],
            'text' => self::defang(mb_substr((string) $file->extracted_text, $section['start'], $section['length'])),
        ];
    }

    /** @return array<string, mixed> the sheet, by name or the first one */
    public static function sheet(AiAttachment $file, ?string $name): array
    {
        $sheets = $file->sheets();
        if ($sheets === []) {
            throw new ToolInputException(__('":file" is not a spreadsheet.', ['file' => $file->original_name]));
        }
        if ($name === null || trim($name) === '') {
            return $sheets[0];
        }
        foreach ($sheets as $sheet) {
            if (mb_strtolower($sheet['name']) === mb_strtolower(trim($name))) {
                return $sheet;
            }
        }

        throw new ToolInputException(__('No sheet ":name" in ":file". Sheets: :list.', [
            'name' => $name, 'file' => $file->original_name, 'list' => implode(', ', array_column($sheets, 'name')),
        ]));
    }

    private static function attr(string $value): string
    {
        return str_replace(['"', '<', '>'], ["'", '(', ')'], $value);
    }
}
