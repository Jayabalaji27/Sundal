<?php

namespace App\Http\Controllers;

use App\Models\ProjectExpense;
use App\Models\ProjectBudget;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseDashboardController extends Controller
{
    protected BudgetService $budgetService;
    
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Get expense dashboard overview
     */
    public function overview(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $baseQuery = ProjectExpense::whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        });

        // Filter by date range if provided
        if ($request->date_from && $request->date_to) {
            $baseQuery->whereBetween('expense_date', [$request->date_from, $request->date_to]);
        }

        $stats = [
            'total_expenses' => (clone $baseQuery)->count(),
            'pending_expenses' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved_expenses' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected_expenses' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'total_amount' => (clone $baseQuery)->sum('amount'),
            'approved_amount' => (clone $baseQuery)->where('status', 'approved')->sum('approved_amount'),
            'pending_amount' => (clone $baseQuery)->where('status', 'pending')->sum('amount'),
        ];

        // Get recent expenses
        $recentExpenses = (clone $baseQuery)
            ->with(['project:id,name', 'submitter:id,name', 'budgetCategory:id,name,color'])
            ->latest()
            ->limit(10)
            ->get();

        // Get expenses by status for chart
        $expensesByStatus = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('status')
            ->get();

        // Get top categories by spending
        $topCategories = (clone $baseQuery)
            ->join('budget_categories', 'project_expenses.budget_category_id', '=', 'budget_categories.id')
            ->where('project_expenses.status', 'approved')
            ->select(
                'budget_categories.id',
                'budget_categories.name',
                'budget_categories.color',
                DB::raw('sum(project_expenses.approved_amount) as total_spent'),
                DB::raw('count(project_expenses.id) as expense_count')
            )
            ->groupBy('budget_categories.id', 'budget_categories.name', 'budget_categories.color')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_expenses' => $recentExpenses,
            'expenses_by_status' => $expensesByStatus,
            'top_categories' => $topCategories
        ]);
    }

    /**
     * Get budget utilization data
     */
    public function budgetUtilization(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $budgets = ProjectBudget::with(['project:id,name', 'categories'])
            ->where('workspace_id', $workspace->id)
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'project_name' => $budget->project->name,
                    'total_budget' => $budget->total_budget,
                    'total_spent' => $budget->total_spent,
                    'remaining_budget' => $budget->remaining_budget,
                    'utilization_percentage' => $budget->utilization_percentage,
                    'status' => $this->getBudgetStatus($budget->utilization_percentage),
                    'categories' => $budget->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'allocated_amount' => $category->allocated_amount,
                            'spent_amount' => $category->total_spent,
                            'utilization_percentage' => $category->utilization_percentage,
                            'is_over_budget' => $category->is_over_budget,
                            'color' => $category->color
                        ];
                    })
                ];
            });

        return response()->json([
            'budgets' => $budgets
        ]);
    }

    /**
     * Get expense trends data
     */
    public function trends(Request $request)
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $period = $request->get('period', 'monthly'); // daily, weekly, monthly
        $months = $request->get('months', 6);
        
        $startDate = now()->subMonths($months)->startOfMonth();
        
        $dateFormat = match($period) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m'
        };
        
        $trends = ProjectExpense::whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })
        ->where('expense_date', '>=', $startDate)
        ->select(
            DB::raw("DATE_FORMAT(expense_date, '{$dateFormat}') as period"),
            DB::raw('count(*) as expense_count'),
            DB::raw('sum(amount) as total_amount'),
            DB::raw('sum(case when status = "approved" then approved_amount else 0 end) as approved_amount'),
            DB::raw('sum(case when status = "pending" then amount else 0 end) as pending_amount')
        )
        ->groupBy('period')
        ->orderBy('period')
        ->get();

        return response()->json([
            'trends' => $trends
        ]);
    }

    /**
     * Get budget alerts
     */
    public function alerts()
    {
        $user = auth()->user();
        $workspace = $user->currentWorkspace;
        
        $alerts = [];
        
        // Project budget alerts
        $projectBudgets = ProjectBudget::with('project:id,name')
            ->where('workspace_id', $workspace->id)
            ->get();
            
        foreach ($projectBudgets as $budget) {
            $utilization = $budget->utilization_percentage;
            
            if ($utilization >= 100) {
                $alerts[] = [
                    'type' => 'budget_exceeded',
                    'severity' => 'critical',
                    'title' => 'Budget Exceeded',
                    'message' => "Project '{$budget->project->name}' has exceeded its budget by " . number_format($utilization - 100, 1) . "%",
                    'project_id' => $budget->project_id,
                    'budget_id' => $budget->id
                ];
            } elseif ($utilization >= 90) {
                $alerts[] = [
                    'type' => 'budget_warning',
                    'severity' => 'warning',
                    'title' => 'Budget Warning',
                    'message' => "Project '{$budget->project->name}' has used " . number_format($utilization, 1) . "% of its budget",
                    'project_id' => $budget->project_id,
                    'budget_id' => $budget->id
                ];
            }
        }
        
        // Category budget alerts
        $categories = \App\Models\BudgetCategory::with(['projectBudget.project:id,name'])
            ->whereHas('projectBudget', function($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })
            ->get();
            
        foreach ($categories as $category) {
            $utilization = $category->utilization_percentage;
            
            if ($utilization >= 100) {
                $alerts[] = [
                    'type' => 'category_exceeded',
                    'severity' => 'critical',
                    'title' => 'Category Budget Exceeded',
                    'message' => "Category '{$category->name}' in project '{$category->projectBudget->project->name}' has exceeded its budget",
                    'project_id' => $category->projectBudget->project_id,
                    'category_id' => $category->id
                ];
            }
        }
        
        // Overdue approvals
        $overdueCount = ProjectExpense::whereHas('project', function($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })
        ->where('status', 'pending')
        ->where('created_at', '<', now()->subDays(7))
        ->count();
        
        if ($overdueCount > 0) {
            $alerts[] = [
                'type' => 'overdue_approvals',
                'severity' => 'warning',
                'title' => 'Overdue Approvals',
                'message' => "{$overdueCount} expense(s) are pending approval for more than 7 days",
                'count' => $overdueCount
            ];
        }
        
        return response()->json([
            'alerts' => $alerts
        ]);
    }

    /**
     * Get budget status based on utilization percentage
     */
    private function getBudgetStatus(float $utilizationPercentage): string
    {
        if ($utilizationPercentage >= 100) {
            return 'over_budget';
        } elseif ($utilizationPercentage >= 90) {
            return 'critical';
        } elseif ($utilizationPercentage >= 75) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
}