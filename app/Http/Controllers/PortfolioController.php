<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $workspaceId = $user->current_workspace_id;

        $portfolios = Portfolio::where('workspace_id', $workspaceId)
            ->withCount('projects')
            ->with('creator:id,name,avatar')
            ->latest()
            ->get();

        return Inertia::render('portfolios/Index', [
            'portfolios' => $portfolios,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color'       => 'nullable|string|max:7',
        ]);

        $user = auth()->user();

        Portfolio::create([
            'workspace_id' => $user->current_workspace_id,
            'name'         => $request->name,
            'description'  => $request->description,
            'color'        => $request->color ?? '#6366f1',
            'created_by'   => $user->id,
        ]);

        return back()->with('success', __('Portfolio created successfully.'));
    }

    public function show(Portfolio $portfolio)
    {
        $user = auth()->user();

        if ($portfolio->workspace_id !== $user->current_workspace_id) {
            abort(404);
        }

        $projects = Project::with(['clients', 'members.user', 'creator'])
            ->where('portfolio_id', $portfolio->id)
            ->latest()
            ->get();

        return Inertia::render('portfolios/Show', [
            'portfolio' => $portfolio,
            'projects'  => $projects,
        ]);
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        $user = auth()->user();

        if ($portfolio->workspace_id !== $user->current_workspace_id) {
            abort(404);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color'       => 'nullable|string|max:7',
        ]);

        $portfolio->update([
            'name'        => $request->name,
            'description' => $request->description,
            'color'       => $request->color ?? $portfolio->color,
        ]);

        return back()->with('success', __('Portfolio updated successfully.'));
    }

    public function destroy(Portfolio $portfolio)
    {
        $user = auth()->user();

        if ($portfolio->workspace_id !== $user->current_workspace_id) {
            abort(404);
        }

        // Detach projects (set portfolio_id to null) before deleting
        Project::where('portfolio_id', $portfolio->id)
            ->update(['portfolio_id' => null]);

        $portfolio->delete();

        return back()->with('success', __('Portfolio deleted successfully.'));
    }
}
