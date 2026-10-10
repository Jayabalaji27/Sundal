<?php

namespace App\Services\Ai\Attachments;

use App\Models\AiAttachment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIO;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\Element;
use PhpOffice\PhpWord\IOFactory as WordIO;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Checks a file is what its name says and reads it, with no AI involved.
 * Pure-PHP libraries only, so it works on shared hosting.
 *
 * Safety: the type is checked from the contents, not the extension;
 * macro-enabled Office files are refused; zip-based files that would
 * expand past a limit are refused; spreadsheet formulas are never
 * calculated (the value Excel saved is used).
 *
 * @phpstan-type Read array{kind: string, status: string, error: ?string, text: ?string, structure: array}
 */
class FileReader
{
    private const MAX_SHEETS = 20;
    private const MAX_COLUMNS = 50;
    private const MAX_CELL_CHARS = 2000;
    private const HEADER_SEARCH_ROWS = 10;

    /** @return Read */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        try {
            $problem = $this->inspect($path, $extension);
            if ($problem !== null) {
                return $this->failed($this->kindOf($extension), $problem);
            }

            return match ($extension) {
                'xlsx', 'xls', 'csv' => $this->spreadsheet($path, $extension),
                'pdf' => $this->pdf($path),
                'docx' => $this->word($path),
                default => $this->plainText($path),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->failed($this->kindOf($extension), __('Sundal could not read this file. It may be damaged or password-protected.'));
        }
    }

    public function kindOf(string $extension): string
    {
        return match (strtolower($extension)) {
            'xlsx', 'xls', 'csv' => AiAttachment::SPREADSHEET,
            'pdf', 'docx' => AiAttachment::DOCUMENT,
            default => AiAttachment::TEXT,
        };
    }

    // ── Is it what it says it is? ────────────────────────────────────────────

    /** @return string|null why the file is refused */
    public function inspect(string $path, string $extension): ?string
    {
        $head = (string) file_get_contents($path, false, null, 0, 8192);

        return match ($extension) {
            'pdf' => str_contains(substr($head, 0, 1024), '%PDF-') ? null : __('This is not a PDF file.'),
            'xlsx' => $this->inspectZip($path, 'xl/workbook.xml', __('This is not an Excel (.xlsx) file.')),
            'docx' => $this->inspectZip($path, 'word/document.xml', __('This is not a Word (.docx) file.')),
            'xls' => $this->inspectXls($path, $head),
            'csv', 'txt', 'md' => str_contains($head, "\0") ? __('This is not a text file.') : null,
            default => __('This file type is not supported.'),
        };
    }

    private function inspectZip(string $path, string $mainPart, string $wrongType): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return $wrongType;
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($mainPart) === false) {
                return $wrongType;
            }
            if ($zip->numFiles > 5000) {
                return __('This file is too complex to read.');
            }

            $unzipped = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $unzipped += (int) ($stat['size'] ?? 0);
                if (str_ends_with(strtolower((string) ($stat['name'] ?? '')), 'vbaproject.bin')) {
                    return __('Files with macros are not accepted. Save it without macros and try again.');
                }
            }
            if ($unzipped > config('ai_assistant.attachments.max_unzipped_mb', 100) * 1024 * 1024) {
                return __('This file is too large once unpacked.');
            }
            if (str_contains(strtolower((string) $zip->getFromName('[Content_Types].xml')), 'macroenabled')) {
                return __('Files with macros are not accepted. Save it without macros and try again.');
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    private function inspectXls(string $path, string $head): ?string
    {
        if (!str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return __('This is not an Excel (.xls) file.');
        }
        // An old-format workbook with a VBA project stream.
        $vba = mb_convert_encoding('_VBA_PROJECT', 'UTF-16LE', 'UTF-8');

        return str_contains((string) file_get_contents($path), $vba)
            ? __('Files with macros are not accepted. Save it without macros and try again.')
            : null;
    }

    // ── Spreadsheets ─────────────────────────────────────────────────────────

    private function spreadsheet(string $path, string $extension): array
    {
        $maxRows = (int) config('ai_assistant.attachments.max_sheet_rows', 1000);
        $reader = SpreadsheetIO::createReader(match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            default => 'Csv',
        });
        if ($extension === 'csv') {
            $reader->setInputEncoding($this->encodingOf($path));
        }

        $info = collect($reader->listWorksheetInfo($path))->keyBy('worksheetName');

        // Only the rows we keep are loaded, so a huge sheet cannot exhaust memory.
        $lastRow = self::HEADER_SEARCH_ROWS + $maxRows + 1;
        $reader->setReadFilter(new class($lastRow) implements IReadFilter {
            public function __construct(private int $lastRow) {}

            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return (int) $row <= $this->lastRow;
            }
        });

        $book = $reader->load($path);
        $sheets = [];
        $text = [];

        foreach (array_slice($book->getAllSheets(), 0, self::MAX_SHEETS) as $sheet) {
            $parsed = $this->sheet($sheet, $maxRows, (int) ($info[$sheet->getTitle()]['totalRows'] ?? 0));
            if ($parsed === null) {
                continue; // empty sheet
            }
            $sheets[] = $parsed;

            $text[] = "## Sheet: {$parsed['name']} ({$parsed['total_rows']} rows)";
            $text[] = implode(' | ', $parsed['headers']);
            foreach (array_slice($parsed['rows'], 0, 20) as $row) {
                $text[] = implode(' | ', $row['c']);
            }
            $text[] = '';
        }
        $book->disconnectWorksheets();

        if ($sheets === []) {
            return $this->failed(AiAttachment::SPREADSHEET, __('This spreadsheet is empty.'));
        }

        $allText = implode("\n", $text);
        $cells = collect($sheets)->sum(fn ($s) => count($s['rows']) * count($s['headers']));

        return [
            'kind' => AiAttachment::SPREADSHEET,
            'status' => AiAttachment::READY,
            'error' => null,
            'text' => $allText,
            'structure' => ['sheets' => $sheets, 'tokens' => (int) ceil($cells * 4 + mb_strlen($allText) / 4)],
        ];
    }

    private function sheet(Worksheet $sheet, int $maxRows, int $totalRowsInFile): ?array
    {
        $highestRow = min($sheet->getHighestDataRow(), self::HEADER_SEARCH_ROWS + $maxRows + 1);
        $highestColumn = min(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()),
            self::MAX_COLUMNS
        );

        $matrix = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $highestColumn; $col++) {
                $cells[] = $this->cellText($sheet, $col, $row);
            }
            if (array_filter($cells, fn ($c) => $c !== '') !== []) {
                $matrix[$row] = $cells;
            }
        }
        if ($matrix === []) {
            return null;
        }

        $headerRow = $this->headerRow($matrix);
        $headers = $this->headers($matrix[$headerRow]);
        $width = count($headers);

        $rows = [];
        foreach ($matrix as $n => $cells) {
            if ($n <= $headerRow) {
                continue;
            }
            if (count($rows) >= $maxRows) {
                break;
            }
            $rows[] = ['n' => $n, 'c' => array_slice($cells, 0, $width)];
        }

        $dataRowsInFile = max(count($rows), $totalRowsInFile - $headerRow);

        return [
            'name' => $sheet->getTitle(),
            'header_row' => $headerRow,
            'headers' => $headers,
            'rows' => $rows,
            'total_rows' => $dataRowsInFile,
            'truncated' => $dataRowsInFile > count($rows),
        ];
    }

    private function cellText(Worksheet $sheet, int $col, int $row): string
    {
        if (!$sheet->cellExists([$col, $row])) {
            return '';
        }
        $cell = $sheet->getCell([$col, $row]);
        $value = $cell->getValue();

        // Never calculate a formula: use the value Excel saved with the file.
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            $value = $cell->getOldCalculatedValue();
        } elseif ($value instanceof RichText) {
            $value = $value->getPlainText();
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_numeric($value) && Date::isDateTimeFormatCode((string) $sheet->getStyle($cell->getCoordinate())->getNumberFormat()->getFormatCode())) {
            try {
                $date = Date::excelToDateTimeObject((float) $value);

                return $date->format('H:i:s') === '00:00:00' ? $date->format('Y-m-d') : $date->format('Y-m-d H:i');
            } catch (Throwable) {
                // Not a usable date: keep the number.
            }
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', str_replace(["\r\n", "\r"], "\n", (string) $value)));

        return mb_substr($text, 0, self::MAX_CELL_CHARS);
    }

    /** The first of the top rows that looks like column names: several cells, mostly words. */
    private function headerRow(array $matrix): int
    {
        foreach (array_slice($matrix, 0, self::HEADER_SEARCH_ROWS, true) as $n => $cells) {
            $filled = array_values(array_filter($cells, fn ($c) => $c !== ''));
            $words = array_filter($filled, fn ($c) => !is_numeric($c) && mb_strlen($c) <= 60);
            if (count($filled) >= 2 && count($words) >= max(2, (int) ceil(count($filled) * 0.6))) {
                return $n;
            }
        }

        return (int) array_key_first($matrix);
    }

    /** @return string[] unique, non-empty column names (trailing empty columns dropped) */
    private function headers(array $cells): array
    {
        while ($cells !== [] && end($cells) === '') {
            array_pop($cells);
        }

        $seen = [];
        $headers = [];
        foreach ($cells as $i => $cell) {
            $name = $cell !== '' ? mb_substr($cell, 0, 80) : __('Column :letter', ['letter' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1)]);
            $key = mb_strtolower($name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $headers[] = $seen[$key] > 1 ? "{$name} ({$seen[$key]})" : $name;
        }

        return $headers;
    }

    // ── Documents ────────────────────────────────────────────────────────────

    private function pdf(string $path): array
    {
        $config = new PdfConfig();
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        $pdf = (new PdfParser([], $config))->parseFile($path);
        $pages = $pdf->getPages();
        $maxPages = (int) config('ai_assistant.attachments.max_pages', 300);

        $pageTexts = [];
        foreach (array_slice($pages, 0, $maxPages) as $page) {
            $pageTexts[] = $this->tidy((string) $page->getText());
        }

        $chars = array_sum(array_map('mb_strlen', $pageTexts));
        // Almost no text on the pages: a scan (photos of pages).
        if (count($pageTexts) > 0 && $chars / count($pageTexts) < 25) {
            return [
                'kind' => AiAttachment::DOCUMENT,
                'status' => AiAttachment::NEEDS_OCR,
                'error' => __('This PDF is a scan with no text. It can be read with a vision-capable AI model (Anthropic, OpenAI or Gemini).'),
                'text' => null,
                'structure' => ['pages' => count($pages), 'sections' => [], 'tokens' => count($pages) * 1500],
            ];
        }

        $parts = [];
        foreach ($pageTexts as $i => $pageText) {
            $parts[] = ['title' => __('Page :n', ['n' => $i + 1]), 'text' => $pageText, 'page' => $i + 1];
        }

        return $this->documentResult($parts, count($pages), byPage: true);
    }

    private function word(string $path): array
    {
        $word = WordIO::load($path, 'Word2007');
        $lines = [];
        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $lines[] = $this->wordBlock($element);
            }
        }

        return $this->textResult(implode('', $lines));
    }

    /** One block-level Word element as text: headings as "#", list items as "-", tables as rows. */
    private function wordBlock(Element\AbstractElement $element): string
    {
        return match (true) {
            $element instanceof Element\Title => "\n" . str_repeat('#', max(1, min(3, (int) $element->getDepth()))) . ' '
                . (is_string($element->getText()) ? $element->getText() : $this->wordInline($element->getText())) . "\n",
            $element instanceof Element\ListItem => '- ' . $element->getTextObject()->getText() . "\n",
            $element instanceof Element\ListItemRun => '- ' . $this->wordInline($element) . "\n",
            $element instanceof Element\Table => $this->wordTable($element) . "\n",
            $element instanceof Element\TextBreak, $element instanceof Element\PageBreak => "\n",
            $element instanceof Element\TextRun, $element instanceof Element\Text, $element instanceof Element\Link => $this->wordInline($element) . "\n",
            $element instanceof Element\AbstractContainer => implode('', array_map(fn ($e) => $this->wordBlock($e), $element->getElements())),
            default => '',
        };
    }

    private function wordInline(mixed $element): string
    {
        if ($element instanceof Element\AbstractContainer) {
            return implode('', array_map(fn ($e) => $this->wordInline($e), $element->getElements()));
        }
        if ($element instanceof Element\TextBreak) {
            return ' ';
        }
        if (is_object($element) && method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_string($text) ? $text : (is_array($text) ? implode('', array_filter($text, 'is_string')) : '');
        }

        return '';
    }

    private function wordTable(Element\Table $table): string
    {
        $rows = [];
        foreach ($table->getRows() as $row) {
            $rows[] = implode(' | ', array_map(fn (Element\Cell $cell) => trim(preg_replace('/\s+/', ' ', implode(' ', array_map(fn ($e) => $this->wordInline($e), $cell->getElements())))), $row->getCells()));
        }

        return implode("\n", $rows);
    }

    private function plainText(string $path): array
    {
        $raw = (string) file_get_contents($path);
        $raw = mb_convert_encoding($raw, 'UTF-8', $this->encodingOf($path));
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        return $this->textResult($raw);
    }

    /** Text with "#" headings (Word, Markdown) or none (plain text): split at headings, then by size. */
    private function textResult(string $text): array
    {
        $text = $this->tidy(mb_substr($text, 0, (int) config('ai_assistant.attachments.max_chars', 400000)));
        if (mb_strlen(trim($text)) === 0) {
            return $this->failed(AiAttachment::DOCUMENT, __('There is no text in this file.'));
        }

        $parts = [];
        $current = ['title' => null, 'text' => ''];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m) && trim($current['text']) !== '') {
                $parts[] = $current;
                $current = ['title' => trim($m[1]), 'text' => ''];
            } elseif (preg_match('/^#{1,3}\s+(.+)$/', $line, $m) && $current['title'] === null) {
                $current['title'] = trim($m[1]);
            }
            $current['text'] .= $line . "\n";
        }
        $parts[] = $current;

        return $this->documentResult($parts, null, byPage: false);
    }

    /**
     * Join the parts into one text and cut it into sections of at most
     * section_chars: consecutive small parts are merged, big ones split at
     * paragraph breaks.
     *
     * @param  array<int, array{title: ?string, text: string, page?: int}>  $parts
     */
    private function documentResult(array $parts, ?int $pageCount, bool $byPage): array
    {
        $limit = (int) config('ai_assistant.attachments.section_chars', 6000);
        $text = '';
        $sections = [];
        $open = null;

        foreach ($parts as $part) {
            foreach ($this->pieces($part['text'], $limit) as $i => $piece) {
                $chunk = $byPage ? "\n--- " . __('Page :n', ['n' => $part['page']]) . " ---\n" . $piece : $piece;
                $used = $open ? mb_strlen($text) - $open['start'] : 0;
                // Word/Markdown: a heading starts a new section once the current one has some text.
                $heading = !$byPage && $i === 0 && $part['title'] !== null;

                if ($open === null || $used + mb_strlen($chunk) > $limit || ($heading && $used > $limit / 3)) {
                    if ($open) {
                        $sections[] = $this->section($open, $text, count($sections));
                    }
                    $open = [
                        'start' => mb_strlen($text),
                        'from' => $part['page'] ?? null,
                        'title' => $byPage ? null : ($part['title'] ?? mb_strimwidth(trim((string) strtok(ltrim($piece), "\n")), 0, 60, '…')),
                    ];
                }
                $open['to'] = $part['page'] ?? null;
                $text .= $chunk;
            }
        }
        if ($open) {
            $sections[] = $this->section($open, $text, count($sections));
        }

        return [
            'kind' => AiAttachment::DOCUMENT,
            'status' => AiAttachment::READY,
            'error' => null,
            'text' => $text,
            'structure' => array_filter([
                'sections' => $sections,
                'pages' => $pageCount,
                'chars' => mb_strlen($text),
                'tokens' => (int) ceil(mb_strlen($text) / 4),
            ], fn ($v) => $v !== null),
        ];
    }

    /** @return array{title: string, start: int, length: int} */
    private function section(array $open, string $text, int $index): array
    {
        $title = match (true) {
            $open['from'] !== null && $open['from'] === $open['to'] => __('Page :n', ['n' => $open['from']]),
            $open['from'] !== null => __('Pages :from–:to', ['from' => $open['from'], 'to' => $open['to']]),
            default => (string) $open['title'],
        };

        return [
            'title' => $title !== '' ? $title : __('Section :n', ['n' => $index + 1]),
            'start' => $open['start'],
            'length' => mb_strlen($text) - $open['start'],
        ];
    }

    /** @return string[] the text in pieces of at most $limit characters, split at paragraphs */
    private function pieces(string $text, int $limit): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $pieces = [];
        $current = '';
        foreach (preg_split('/(?<=\n\n)/', $text) as $paragraph) {
            while (mb_strlen($paragraph) > $limit) {
                $pieces[] = $current . mb_substr($paragraph, 0, $limit - mb_strlen($current));
                $paragraph = mb_substr($paragraph, $limit - mb_strlen($current));
                $current = '';
            }
            if (mb_strlen($current) + mb_strlen($paragraph) > $limit) {
                $pieces[] = $current;
                $current = '';
            }
            $current .= $paragraph;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return array_values(array_filter($pieces, fn ($p) => trim($p) !== ''));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t", "\u{00A0}"], ["\n", "\n", ' ', ' '], $text);
        $text = preg_replace('/[ ]{2,}/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string) $text) . "\n";
    }

    private function encodingOf(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 65536);

        return mb_detect_encoding($sample, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true) ?: 'UTF-8';
    }

    private function failed(string $kind, string $error): array
    {
        return ['kind' => $kind, 'status' => AiAttachment::FAILED, 'error' => $error, 'text' => null, 'structure' => []];
    }
}
