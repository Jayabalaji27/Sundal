<?php

namespace App\Services;

use App\Models\ProjectExpense;
use App\Models\ProjectBudget;
use App\Models\BudgetCategory;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Update budget calculations after expense approval
     */
    public function updateBudgetAfterApproval(ProjectExpense $expense): void
    {
        $expense->load(['project.budget', 'budgetCategory']);
        
        // Only update if relationships exist
        if ($expense->project && $expense->project->budget) {
            $this->updateProjectBudget($expense);
        }
        
        if ($expense->budgetCategory) {
            $this->updateCategoryBudget($expense);
        }
        
        // Check for budget alerts
        $this->checkBudgetAlerts($expense);
    }
    
    /**
     * Update project budget spent amounts
     */
    private function updateProjectBudget(ProjectExpense $expense): void
    {
        if (!$expense->project || !$expense->project->budget) {
            return;
        }
        
        $projectBudget = $expense->project->budget;
        if (!$projectBudget || is_string($projectBudget)) {
            return;
        }
        
        $totalSpent = $expense->project->expenses()->approved()->sum('amount');
        
        // Calculate utilization percentage
        $utilizationPercentage = $projectBudget->total_budget > 0 
            ? ($totalSpent / $projectBudget->total_budget) * 100 
            : 0;
            
        // You can store these values in cache or update budget table if needed
        cache()->put("budget_spent_{$projectBudget->id}", $totalSpent, now()->addHours(1));
        cache()->put("budget_utilization_{$projectBudget->id}", $utilizationPercentage, now()->addHours(1));
    }
    
    /**
     * Update category budget spent amounts
     */
    private function updateCategoryBudget(ProjectExpense $expense): void
    {
        if (!$expense->budgetCategory) {
            return;
        }
        
        $category = $expense->budgetCategory;
        $categorySpent = $category->expenses()->approved()->sum('amount');
        
        // Calculate category utilization
        $utilizationPercentage = $category->allocated_amount > 0 
            ? ($categorySpent / $category->allocated_amount) * 100 
            : 0;
            
        // Cache category spending data
        cache()->put("category_spent_{$category->id}", $categorySpent, now()->addHours(1));
        cache()->put("category_utilization_{$category->id}", $utilizationPercentage, now()->addHours(1));
    }
    
    /**
     * Check for budget alerts and notifications
     */
    private function checkBudgetAlerts(ProjectExpense $expense): void
    {
        // Check project budget alerts
        if ($expense->project && $expense->project->budget && !is_string($expense->project->budget)) {
            $projectBudget = $expense->project->budget;
            $utilizationPercentage = cache()->get("budget_utilization_{$projectBudget->id}", 0);
            
            if ($utilizationPercentage >= 90) {
                // Trigger critical budget alert
                $this->triggerBudgetAlert($projectBudget, 'critical', $utilizationPercentage);
            } elseif ($utilizationPercentage >= 75) {
                // Trigger warning budget alert
                $this->triggerBudgetAlert($projectBudget, 'warning', $utilizationPercentage);
            }
        }
        
        // Check category budget alerts
        if ($expense->budgetCategory) {
            $category = $expense->budgetCategory;
            $utilizationPercentage = $category->utilization_percentage;
            
            if ($utilizationPercentage >= 100) {
                // Category over budget
                $this->triggerCategoryAlert($category, 'over_budget', $utilizationPercentage);
            } elseif ($utilizationPercentage >= 90) {
                // Category near budget limit
                $this->triggerCategoryAlert($category, 'near_limit', $utilizationPercentage);
            }
        }
    }
    
    /**
     * Trigger budget alert notification
     */
    private function triggerBudgetAlert(ProjectBudget $budget, string $type, float $percentage): void
    {
        // You can implement notification logic here
        // For example: send email, create notification record, etc.
        
        // Log the alert for now
        logger()->info("Budget Alert: Project {$budget->project->name} is at {$percentage}% utilization", [
            'project_id' => $budget->project_id,
            'budget_id' => $budget->id,
            'type' => $type,
            'percentage' => $percentage
        ]);
    }
    
    /**
     * Trigger category alert notification
     */
    private function triggerCategoryAlert(BudgetCategory $category, string $type, float $percentage): void
    {
        // Log the category alert
        logger()->info("Category Alert: {$category->name} is at {$percentage}% utilization", [
            'category_id' => $category->id,
            'project_budget_id' => $category->project_budget_id,
            'type' => $type,
            'percentage' => $percentage
        ]);
    }
    
    /**
     * Get budget summary for a project
     */
    public function getProjectBudgetSummary(int $projectId): array
    {
        $projectBudget = ProjectBudget::where('project_id', $projectId)->first();
        
        if (!$projectBudget) {
            return [];
        }
        
        $totalSpent = $projectBudget->total_spent;
        $remainingBudget = $projectBudget->remaining_budget;
        $utilizationPercentage = $projectBudget->utilization_percentage;
        
        // Get category breakdown
        $categories = $projectBudget->categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'allocated_amount' => $category->allocated_amount,
                'spent_amount' => $category->total_spent,
                'remaining_amount' => $category->remaining_amount,
                'utilization_percentage' => $category->utilization_percentage,
                'is_over_budget' => $category->is_over_budget,
                'color' => $category->color
            ];
        });
        
        return [
            'total_budget' => $projectBudget->total_budget,
            'total_spent' => $totalSpent,
            'remaining_budget' => $remainingBudget,
            'utilization_percentage' => $utilizationPercentage,
            'currency' => $projectBudget->currency,
            'categories' => $categories,
            'status' => $this->getBudgetStatus($utilizationPercentage)
        ];
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