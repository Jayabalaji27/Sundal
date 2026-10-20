<?php

namespace App\Exports;

use App\Services\Reports\ReportDocument;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** A report as a workbook: an overview sheet, then one sheet per table. */
class ReportWorkbookExport implements WithMultipleSheets
{
    public function __construct(private ReportDocument $doc)
    {
    }

    public function sheets(): array
    {
        $overview = [];
        if ($this->doc->subtitle) {
            $overview[] = [$this->doc->subtitle];
        }
        $overview[] = [];
        foreach ($this->doc->meta as $label => $value) {
            $overview[] = [$label, $value];
        }
        if ($this->doc->summary) {
            $overview[] = [];
            $overview[] = ['Summary'];
            foreach ($this->doc->summary as $label => $value) {
                $overview[] = [$label, $value];
            }
        }
        $overview[] = [];
        $overview[] = ['Generated on', now()->format('Y-m-d H:i')];

        // The report title doubles as the overview sheet's heading row.
        $sheets = [new ReportSheetExport('Overview', [$this->doc->title], $overview, titleRow: true)];
        $used = ['overview'];
        foreach ($this->doc->sections as $section) {
            $title = $this->sheetTitle($section['title'], $used);
            $rows = $section['rows'] ?: [[$section['empty']]];
            $sheets[] = new ReportSheetExport($title, $section['columns'], $rows);
        }

        return $sheets;
    }

    /** Excel sheet names: at most 31 characters, none of []:*?/\, unique in the workbook. */
    private function sheetTitle(string $title, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[\[\]:*?\/\\\\]+/', ' ', $title)) ?: 'Sheet', 0, 28);
        $name = $base;
        for ($i = 2; in_array(mb_strtolower($name), $used, true); $i++) {
            $name = $base . ' ' . $i;
        }
        $used[] = mb_strtolower($name);

        return $name;
    }
}
