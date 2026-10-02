<?php

namespace App\Http\Controllers;

use App\Models\ProjectExpense;
use App\Models\ProjectBudget;
use App\Models\BudgetCategory;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpenseReportController extends Controller
{
    public function budgetVsActual(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $query = ProjectBudget::with(['project', 'categories'])
            ->where('workspace_id', $workspace->id);

        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->period) {
            $query->where('period_type', $request->period);
        }

        $budgets = $query->get();

        $reportData = $budgets->map(function ($budget) {
            return [
                'project' => $budget->project->title,
                'total_budget' => $budget->total_budget,
                'total_spent' => $budget->total_spent,
                'remaining' => $budget->remaining_budget,
                'utilization' => $budget->utilization_percentage,
                'categories' => $budget->categories->map(function ($category) {
                    return [
                        'name' => $category->name,
                        'allocated' => $category->allocated_amount,
                        'spent' => $category->total_spent,
                        'remaining' => $category->remaining_amount,
                        'utilization' => $category->utilization_percentage
                    ];
                })
            ];
        });

        return response()->json([
            'report_data' => $reportData,
            'summary' => [
                'total_budgets' => $budgets->sum('total_budget'),
                'total_spent' => $budgets->sum('total_spent'),
                'average_utilization' => $budgets->avg('utilization_percentage')
            ]
        ]);
    }

    public function categoryReport(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $categories = BudgetCategory::whereHas('projectBudget', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->with(['projectBudget.project', 'expenses' => function($q) {
            $q->where('status', 'approved');
        }])->get();

        $reportData = $categories->groupBy('name')->map(function ($categoryGroup, $name) {
            $totalAllocated = $categoryGroup->sum('allocated_amount');
            $totalSpent = $categoryGroup->sum('total_spent');
            
            return [
                'name' => $name,
                'total_allocated' => $totalAllocated,
                'total_spent' => $totalSpent,
                'utilization' => $totalAllocated > 0 ? ($totalSpent / $totalAllocated) * 100 : 0,
                'projects' => $categoryGroup->map(function ($category) {
                    return [
                        'project' => $category->projectBudget->project->title,
                        'allocated' => $category->allocated_amount,
                        'spent' => $category->total_spent
                    ];
                })
            ];
        });

        return response()->json(['report_data' => $reportData]);
    }

    public function teamReport(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        $expenses = ProjectExpense::with(['submitter', 'project', 'budgetCategory'])
            ->whereHas('project', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->get();

        $reportData = $expenses->groupBy('submitted_by')->map(function ($userExpenses, $userId) {
            $user = $userExpenses->first()->submitter;
            
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar
                ],
                'total_expenses' => $userExpenses->count(),
                'total_amount' => $userExpenses->sum('amount'),
                'categories' => $userExpenses->groupBy('budget_category_id')->map(function ($categoryExpenses, $categoryId) {
                    $category = $categoryExpenses->first()->budgetCategory;
                    return [
                        'name' => $category ? $category->name : 'Uncategorized',
                        'count' => $categoryExpenses->count(),
                        'amount' => $categoryExpenses->sum('amount')
                    ];
                })->values(),
                'projects' => $userExpenses->groupBy('project_id')->map(function ($projectExpenses, $projectId) {
                    $project = $projectExpenses->first()->project;
                    return [
                        'name' => $project->title,
                        'count' => $projectExpenses->count(),
                        'amount' => $projectExpenses->sum('amount')
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'report_data' => $reportData,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ]
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->type; // 'budget_vs_actual', 'category', 'team'
        $format = $request->format; // 'pdf', 'excel', 'csv'
        
        // This would implement actual export functionality
        // For now, return success response
        return response()->json(['message' => 'Export functionality coming soon']);
    }
}