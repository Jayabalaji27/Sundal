<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\ProjectBudget;
use Illuminate\Http\Request;

class BudgetCategoryController extends Controller
{
    public function index(ProjectBudget $budget)
    {
        $categories = $budget->categories()->orderBy('sort_order')->get();
        
        return response()->json([
            'categories' => $categories
        ]);
    }

    public function store(Request $request, ProjectBudget $budget)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0',
            'color' => 'nullable|string|size:7',
            'description' => 'nullable|string'
        ]);

        $maxOrder = $budget->categories()->max('sort_order') ?? 0;

        $category = BudgetCategory::create([
            'project_budget_id' => $budget->id,
            'name' => $validated['name'],
            'allocated_amount' => $validated['allocated_amount'],
            'color' => $validated['color'] ?? '#3B82F6',
            'description' => $validated['description'],
            'sort_order' => $maxOrder + 1
        ]);

        return response()->json(['category' => $category]);
    }

    public function update(Request $request, BudgetCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'allocated_amount' => 'required|numeric|min:0',
            'color' => 'nullable|string|size:7',
            'description' => 'nullable|string'
        ]);

        $category->update($validated);

        return response()->json(['category' => $category]);
    }

    public function destroy(BudgetCategory $category)
    {
        $category->delete();
        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, ProjectBudget $budget)
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:budget_categories,id',
            'categories.*.sort_order' => 'required|integer|min:1'
        ]);

        foreach ($validated['categories'] as $categoryData) {
            BudgetCategory::where('id', $categoryData['id'])
                ->where('project_budget_id', $budget->id)
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json(['success' => true]);
    }
}