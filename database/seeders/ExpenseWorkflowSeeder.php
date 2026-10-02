<?php

namespace Database\Seeders;

use App\Models\ProjectExpense;
use App\Models\ExpenseWorkflow;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseWorkflowSeeder extends Seeder
{
    public function run()
    {
        $expenses = ProjectExpense::where('amount', '>', 500)->get();
        $approvers = User::limit(3)->get();
        
        foreach ($expenses as $expense) {
            // Create multi-step approval workflow for high-value expenses
            foreach ($approvers as $index => $approver) {
                ExpenseWorkflow::create([
                    'project_expense_id' => $expense->id,
                    'step' => $index + 1,
                    'approver_id' => $approver->id,
                    'status' => $index === 0 ? 'pending' : 'waiting',
                    'notes' => null,
                    'processed_at' => null
                ]);
            }
        }
    }
}