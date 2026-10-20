<?php

namespace App\Services\Reports;

/**
 * A report described once - title, a few header facts, headline figures and
 * tables - so ReportRenderer can turn the same content into a PDF, an Excel
 * workbook or a Word document without each report re-implementing every format.
 */
class ReportDocument
{
    /** @var array<string, string> Header facts, e.g. "Period" => "2026-09-01 to 2026-09-30". */
    public array $meta = [];

    /** @var array<string, string|int|float> Headline figures, e.g. "Total hours" => 412.5. */
    public array $summary = [];

    /** @var array<int, array{title: string, columns: string[], rows: array<int, array<int, mixed>>, empty: string}> */
    public array $sections = [];

    public function __construct(
        public string $title,
        public string $filename,
        public ?string $subtitle = null,
    ) {
    }

    public function meta(string $label, ?string $value): static
    {
        $this->meta[$label] = $value === null || $value === '' ? '-' : $value;

        return $this;
    }

    public function summary(string $label, string|int|float|null $value): static
    {
        $this->summary[$label] = $value ?? '-';

        return $this;
    }

    /**
     * @param  string[]  $columns
     * @param  iterable<int, array<int, mixed>>  $rows  One array per row, in column order.
     */
    public function table(string $title, array $columns, iterable $rows, string $empty = 'No records for this period.'): static
    {
        $this->sections[] = [
            'title' => $title,
            'columns' => $columns,
            'rows' => collect($rows)->map(fn ($row) => array_values((array) $row))->all(),
            'empty' => $empty,
        ];

        return $this;
    }
}
