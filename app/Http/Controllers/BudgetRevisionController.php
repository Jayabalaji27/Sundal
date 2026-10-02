<?php

namespace App\Http\Controllers;

use App\Models\BudgetRevision;
use App\Models\ProjectBudget;
use Illuminate\Http\Request;

class BudgetRevisionController extends Controller
{
    public function store(Request $request, ProjectBudget $budget)
    {
        $validated = $request->validate([
            'new_amount' => 'required|numeric|min:0',
            'reason' => 'required|string'
        ]);

        $revision = BudgetRevision::create([
            'project_budget_id' => $budget->id,
            'revised_by' => auth()->id(),
            'previous_amount' => $budget->total_budget,
            'new_amount' => $validated['new_amount'],
            'reason' => $validated['reason'],
            'status' => 'pending'
        ]);

        return back();
    }

    public function approve(BudgetRevision $revision)
    {
        $revision->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

        // Update the actual budget
        $revision->projectBudget->update([
            'total_budget' => $revision->new_amount
        ]);

        return back();
    }

    public function reject(Request $request, BudgetRevision $revision)
    {
        $revision->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now()
        ]);

        return back();
    }
}