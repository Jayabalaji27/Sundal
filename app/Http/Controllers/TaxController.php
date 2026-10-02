<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends BaseController
{
    public function index()
    {
        $taxes = Tax::where('workspace_id', auth()->user()->current_workspace_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('taxes/Index', [
            'taxes' => $taxes
        ]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->current_workspace_id) {
            return back()->withErrors(['error' => __('No workspace found. Please select a workspace.')]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        Tax::create([
            'name' => $request->name,
            'rate' => $request->rate,
            'workspace_id' => auth()->user()->current_workspace_id,
        ]);

        return redirect()->back()->with('success', 'Tax created successfully.');
    }

    public function update(Request $request, Tax $tax)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $tax->update([
            'name' => $request->name,
            'rate' => $request->rate,
        ]);

        return redirect()->back()->with('success', 'Tax updated successfully.');
    }

    public function destroy(Tax $tax)
    {
        $tax->delete();

        return redirect()->back()->with('success', 'Tax deleted successfully.');
    }
}