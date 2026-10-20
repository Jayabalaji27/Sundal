<?php

namespace App\Services\Reports;

use App\Exports\ReportWorkbookExport;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings as WordSettings;

/** Turns a ReportDocument into a PDF, Excel workbook or Word document. */
class ReportRenderer
{
    public const FORMATS = ['pdf', 'xlsx', 'docx'];

    /**
     * Most table rows PDF and Word documents hold, shared across all tables.
     * dompdf needs roughly 0.4 MB per row (1,000 rows peaked at 440 MB), so an
     * uncapped year of timesheet entries would exhaust memory; Excel always
     * gets every row and the trimmed tables point to it.
     */
    private const ROW_BUDGET = ['pdf' => 400, 'docx' => 1500];

    private const MIME = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function mime(string $format): string
    {
        return self::MIME[$format];
    }

    public static function filename(string $base, string $format): string
    {
        $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $base)) ?: 'report';

        return $base . '.' . $format;
    }

    public function render(ReportDocument $doc, string $format): RenderedReport
    {
        if (isset(self::ROW_BUDGET[$format])) {
            $doc = clone $doc;
            $doc->sections = $this->fitRows($doc->sections, self::ROW_BUDGET[$format]);
            $this->ensureMemory(512 * 1024 * 1024);
        }

        $content = match ($format) {
            'pdf' => $this->pdf($doc),
            'xlsx' => Excel::raw(new ReportWorkbookExport($doc), \Maatwebsite\Excel\Excel::XLSX),
            'docx' => $this->docx($doc),
        };

        return new RenderedReport($content, self::filename($doc->filename, $format), self::mime($format));
    }

    /** Cell text for PDF and Word; Excel keeps the raw values so numbers stay numbers. */
    public static function display(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '-',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_float($value) => number_format($value, 2),
            is_int($value) => number_format($value),
            default => preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value),
        };
    }

    /** The workspace's theme colour, the same one the project report PDF uses. */
    public static function brandColor(): string
    {
        $map = ['blue' => '#3b82f6', 'green' => '#10B77F', 'purple' => '#8b5cf6', 'orange' => '#f97316', 'red' => '#ef4444'];
        $workspace = auth()->user()?->currentWorkspace;
        if (!$workspace) {
            return '#10B77F';
        }

        $settings = Setting::where('user_id', $workspace->owner_id)->where('workspace_id', $workspace->id)
            ->whereIn('key', ['themeColor', 'customColor'])->pluck('value', 'key');
        $theme = $settings['themeColor'] ?? 'green';
        $color = $theme === 'custom' ? ($settings['customColor'] ?? '#10B77F') : ($map[$theme] ?? '#10B77F');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#10B77F';
    }

    /**
     * Shares the row budget across tables, smallest first, so a huge activity
     * log can't crowd out a short task list. Trimmed tables get a note.
     */
    private function fitRows(array $sections, int $budget): array
    {
        $order = array_keys($sections);
        usort($order, fn ($a, $b) => count($sections[$a]['rows']) <=> count($sections[$b]['rows']));

        $tablesLeft = count($order);
        foreach ($order as $i) {
            $share = intdiv($budget, max(1, $tablesLeft--));
            $total = count($sections[$i]['rows']);
            if ($total > $share) {
                $sections[$i]['rows'] = array_slice($sections[$i]['rows'], 0, $share);
                $sections[$i]['note'] = __('Showing the first :shown of :total rows. Download the Excel version for the full list.', [
                    'shown' => number_format($share),
                    'total' => number_format($total),
                ]);
            }
            $budget -= count($sections[$i]['rows']);
        }

        return $sections;
    }

    /** Raises PHP's memory limit to at least $bytes for this request; never lowers it. */
    private function ensureMemory(int $bytes): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }

        $current = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        if ($current < $bytes) {
            @ini_set('memory_limit', (string) $bytes);
        }
    }

    private function pdf(ReportDocument $doc): string
    {
        // Embed only the glyphs used: a 110-row report drops from ~870 KB to ~35 KB,
        // which matters once the file is sent as an email attachment.
        return Pdf::loadView('pdf.report', ['doc' => $doc, 'primaryColor' => self::brandColor()])
            ->setPaper('a4', 'landscape')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    private function docx(ReportDocument $doc): string
    {
        // PhpWord writes text into the XML as-is unless told to escape it, so a
        // "&" in a task title would otherwise produce a document Word can't open.
        WordSettings::setOutputEscapingEnabled(true);

        $brand = ltrim(self::brandColor(), '#');
        $word = new PhpWord();
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(10);
        $page = $word->addSection(['orientation' => 'landscape', 'marginLeft' => 720, 'marginRight' => 720, 'marginTop' => 720, 'marginBottom' => 720]);

        $page->addText(self::display($doc->title), ['size' => 18, 'bold' => true], ['spaceAfter' => 60]);
        if ($doc->subtitle) {
            $page->addText(self::display($doc->subtitle), ['size' => 10, 'color' => '6B7280'], ['spaceAfter' => 160]);
        }

        if ($doc->meta) {
            $meta = $page->addTable(['cellMargin' => 40]);
            foreach ($doc->meta as $label => $value) {
                $meta->addRow();
                $meta->addCell(2600)->addText(self::display($label), ['color' => '6B7280']);
                $meta->addCell(9000)->addText(self::display($value));
            }
            $page->addTextBreak();
        }

        if ($doc->summary) {
            $summary = $page->addTable(['borderSize' => 6, 'borderColor' => 'E5E7EB', 'cellMargin' => 100, 'alignment' => 'center']);
            $width = (int) floor(14400 / max(1, count($doc->summary)));
            $summary->addRow();
            foreach ($doc->summary as $value) {
                $summary->addCell($width)->addText(self::display($value), ['size' => 14, 'bold' => true, 'color' => $brand], ['alignment' => 'center']);
            }
            $summary->addRow();
            foreach (array_keys($doc->summary) as $label) {
                $summary->addCell($width)->addText(strtoupper(self::display($label)), ['size' => 8, 'color' => '6B7280'], ['alignment' => 'center']);
            }
            $page->addTextBreak();
        }

        foreach ($doc->sections as $section) {
            $page->addText(self::display($section['title']), ['size' => 13, 'bold' => true], ['spaceBefore' => 200, 'spaceAfter' => 100]);
            if (!$section['rows']) {
                $page->addText(self::display($section['empty']), ['italic' => true, 'color' => '6B7280']);
                continue;
            }

            $table = $page->addTable(['borderSize' => 6, 'borderColor' => 'E5E7EB', 'cellMargin' => 60]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach ($section['columns'] as $column) {
                $table->addCell(null, ['bgColor' => 'F3F4F6'])->addText(self::display($column), ['bold' => true, 'size' => 9]);
            }
            foreach ($section['rows'] as $row) {
                $table->addRow(null, ['cantSplit' => true]);
                foreach ($row as $cell) {
                    $table->addCell()->addText(self::display($cell), ['size' => 9]);
                }
            }
            if (!empty($section['note'])) {
                $page->addText(self::display($section['note']), ['italic' => true, 'size' => 8, 'color' => '6B7280'], ['spaceBefore' => 60]);
            }
        }

        $page->addTextBreak();
        $page->addText('Generated on ' . now()->format('M d, Y H:i'), ['size' => 8, 'color' => '9CA3AF']);

        $path = tempnam(sys_get_temp_dir(), 'report');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
