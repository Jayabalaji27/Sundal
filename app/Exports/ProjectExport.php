<?php

namespace App\Exports;

use App\Models\Project;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class ProjectExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function query()
    {
        $user = auth()->user();

        // Same scoping as ProjectController::index — workspace isolation
        // and per-role visibility must match exactly, or export could leak
        // projects a user can't otherwise see.
        $query = Project::with(['workspace', 'clients'])
            ->forWorkspace($user->current_workspace_id)
            ->visibleTo($user);

        if ($this->request) {
            if ($this->request->search) {
                $query->search($this->request->search);
            }
            if ($this->request->status && $this->request->status !== 'all') {
                $query->byStatus($this->request->status);
            }
            if ($this->request->priority && $this->request->priority !== 'all') {
                $query->byPriority($this->request->priority);
            }
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'Title',
            'Status',
            'Priority',
            'Progress (%)',
            'Start Date',
            'Deadline',
            'Budget',
            'Client(s)',
            'Created At',
        ];
    }

    public function map($project): array
    {
        return [
            $project->title,
            $project->status,
            $project->priority,
            $project->progress,
            optional($project->start_date)->format('Y-m-d'),
            optional($project->deadline)->format('Y-m-d'),
            $project->budget,
            $project->clients->pluck('name')->implode(', '),
            $project->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
