<?php

namespace App\Exports;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The Tasks page list as a spreadsheet. The column names are the ones the
 * Tasks import recognises, so an exported file can be edited and imported
 * (into another project, say) without mapping columns by hand.
 */
class TaskExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
    /** @param  Builder<Task>  $query  already scoped to what the user may see */
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
            'ID', 'Project', 'Title', 'Description', 'Type', 'Priority', 'Severity', 'Stage', 'Assignee',
            'Start Date', 'Due Date', 'Story Points', 'Milestone', 'Progress %', 'Created By', 'Created At',
        ];
    }

    /** @param  Task  $task */
    public function map($task): array
    {
        return [
            $task->id,
            $task->project?->title,
            $task->title,
            $task->description,
            $task->type,
            $task->priority,
            $task->severity,
            $task->taskStage?->name,
            // The email, not the name: it matches exactly one person on import.
            $task->assignedTo?->email,
            optional($task->start_date)->format('Y-m-d'),
            optional($task->end_date)->format('Y-m-d'),
            $task->story_points,
            $task->milestone?->title,
            (int) $task->progress,
            $task->creator?->name,
            optional($task->created_at)->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true]]];
    }
}
