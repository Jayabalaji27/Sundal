<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class InvoiceExport implements FromQuery, WithHeadings, WithMapping
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;

        if (!$workspace) {
            return Invoice::whereRaw('1 = 0');
        }

        $userWorkspaceRole = $workspace->getMemberRole($user);

        // Check if user is workspace owner (override role if needed)
        if ($user->id === $workspace->owner_id) {
            $userWorkspaceRole = 'owner';
        }

        $query = Invoice::with(['project', 'client'])
            ->where('workspace_id', $workspace->id);

        // Access control logic similar to InvoiceController@index
        if ($userWorkspaceRole === 'owner') {
            // Owner: Access to all invoices in the workspace
        } elseif ($userWorkspaceRole === 'client') {
            // Clients see all invoices assigned to them
            $query->where('client_id', $user->id);
        } elseif (in_array($userWorkspaceRole, ['manager', 'member'])) {
            $query->where(function ($q) use ($user, $userWorkspaceRole) {
                // Show non-draft invoices where user is member or creator of the project
                $q->where('status', '!=', 'draft')
                    ->whereHas('project', function ($projQ) use ($user) {
                        $projQ->where(function ($projectQuery) use ($user) {
                            $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                                $memberQuery->where('user_id', $user->id);
                            })->orWhere('created_by', $user->id);
                        });
                    });

                // Show draft invoices only where user is manager and is project member/creator
                if ($userWorkspaceRole === 'manager') {
                    $q->orWhere('status', 'draft')
                        ->whereHas('project', function ($projQ) use ($user) {
                            $projQ->where(function ($projectQuery) use ($user) {
                                $projectQuery->whereHas('members', function ($memberQuery) use ($user) {
                                    $memberQuery->where('user_id', $user->id);
                                })->orWhere('created_by', $user->id);
                            });
                        });
                }
            });
        } else {
            // No access for other roles
            return Invoice::whereRaw('1 = 0');
        }

        if ($this->request->filled('search')) {
            $search = $this->request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if ($this->request->filled('project_id')) {
            $query->where('project_id', $this->request->project_id);
        }

        if ($this->request->filled('client_id')) {
            $query->where('client_id', $this->request->client_id);
        }

        if ($this->request->filled('status')) {
            $query->where('status', $this->request->status);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Title',
            'Project',
            'Client',
            'Total Amount',
            'Status',
            'Invoice Date',
            'Due Date',
            'Created At'
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->title,
            $invoice->project->title ?? '',
            $invoice->client->name ?? '',
            $invoice->total_amount,
            $invoice->status,
            $invoice->invoice_date,
            $invoice->due_date,
            $invoice->created_at->format('Y-m-d H:i:s')
        ];
    }
}