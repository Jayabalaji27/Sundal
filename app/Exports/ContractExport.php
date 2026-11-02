<?php

namespace App\Exports;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** The Contracts page list as a spreadsheet. */
class ContractExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
    /** @param  Builder<Contract>  $query  already scoped to what the user may see */
    public function __construct(private Builder $query)
    {
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Contract ID', 'Subject', 'Client', 'Client Email', 'Type', 'Project', 'Value', 'Currency',
            'Start Date', 'End Date', 'Status', 'Created By', 'Created At',
        ];
    }

    /** @param  Contract  $contract */
    public function map($contract): array
    {
        return [
            $contract->contract_id,
            $contract->subject,
            $contract->client?->name,
            $contract->client?->email,
            $contract->contractType?->name,
            $contract->project?->title,
            $contract->contract_value !== null ? (float) $contract->contract_value : null,
            $contract->currency,
            optional($contract->start_date)->format('Y-m-d'),
            optional($contract->end_date)->format('Y-m-d'),
            ucfirst((string) $contract->status),
            $contract->creator?->name,
            optional($contract->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
