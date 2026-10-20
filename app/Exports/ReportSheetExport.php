<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One sheet of ReportWorkbookExport. */
class ReportSheetExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
    public function __construct(
        private string $title,
        private array $columns,
        private array $rows,
        private bool $titleRow = false,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet)
    {
        if ($this->titleRow) {
            return [1 => ['font' => ['bold' => true, 'size' => 14]], 'A' => ['font' => ['bold' => true]]];
        }

        $sheet->freezePane('A2');

        return [1 => [
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F3F4F6']],
        ]];
    }
}
