<?php

namespace Database\Seeders;

use App\Models\ProjectBudget;
use App\Models\ProjectExpense;
use Illuminate\Database\Seeder;

class BudgetAlertSeeder extends Seeder
{
    public function run()
    {
        $budgets = ProjectBudget::with('categories')->get();
        
        foreach ($budgets as $budget) {
            // Create some expenses to trigger alerts
            foreach ($budget->categories as $category) {
                if (rand(1, 100) > 70) { // 30% chance to create high utilization
                    $alertAmount = $category->allocated_amount * 0.85; // 85% utilization
                    
                    ProjectExpense::create([
                        'project_id' => $budget->project_id,
                        'budget_category_id' => $category->id,
                        'submitted_by' => 1,
                        'amount' => $alertAmount,
                        'currency' => $budget->currency,
                        'expense_date' => now()->subDays(rand(1, 30)),
                        'title' => 'High utilization expense for ' . $category->name,
                        'description' => 'Sample expense to trigger budget alert',
                        'status' => 'approved'
                    ]);
                }
            }
        }
    }
}