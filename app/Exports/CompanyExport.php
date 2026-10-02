<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Http\Request;

class CompanyExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = User::where('type', 'company')
                    ->whereHas('workspaces', function ($q) {
                        $q->where('role', 'owner');
                    })
                    ->with('plan');

        // Apply filters if request is provided
        if ($this->request) {
            if ($this->request->has('search') && !empty($this->request->search)) {
                $query->where(function($q) {
                    $q->where('name', 'like', "%{$this->request->search}%")
                      ->orWhere('email', 'like', "%{$this->request->search}%");
                });
            }
            
            if ($this->request->has('status') && $this->request->status !== 'all') {
                $query->where('status', $this->request->status);
            }
            
            if ($this->request->has('start_date') && !empty($this->request->start_date)) {
                $query->whereDate('created_at', '>=', $this->request->start_date);
            }
            
            if ($this->request->has('end_date') && !empty($this->request->end_date)) {
                $query->whereDate('created_at', '<=', $this->request->end_date);
            }
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Password',
            'Status',
            'Plan',
            'Created At',
            'Updated At',
        ];
    }

    public function map($company): array
    {
        return [
            $company->name,
            $company->email,
            '****', // Don't export actual passwords
            $company->status,
            $company->plan ? $company->plan->name : 'No Plan',
            $company->created_at->format('Y-m-d H:i:s'),
            $company->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}