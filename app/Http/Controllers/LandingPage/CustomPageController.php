<?php

namespace App\Http\Controllers\LandingPage;

use App\Http\Controllers\Controller;
use App\Models\LandingPageCustomPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CustomPageController extends Controller
{
    public function index(Request $request)
    {
        $query = LandingPageCustomPage::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');

        if (in_array($sortField, ['title', 'created_at', 'sort_order'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->ordered();
        }

        $pages = $query->paginate($request->get('per_page', 10))
            ->withQueryString();

        return Inertia::render('landing-page/custom-pages/index', [
            'pages' => $pages,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }

    public function create()
    {
        return Inertia::render('landing-page/custom-pages/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:landing_page_custom_pages,title',
            'content' => 'required|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer'
        ]);

        // Generate slug
        $slug = Str::slug($request->title);

        // Check duplicate slug
        $count = LandingPageCustomPage::where('slug', $slug)->count();
        if ($count > 0) {
            $slug = $slug . '-' . ($count + 1);
        }

        $validated['slug'] = $slug;

        LandingPageCustomPage::create($validated);

        return redirect()->route('landing-page.custom-pages.index')->with('success', __('Custom page created successfully!'));
    }

    public function edit(LandingPageCustomPage $customPage)
    {
        return Inertia::render('landing-page/custom-pages/edit', [
            'page' => $customPage
        ]);
    }
    public function update(Request $request, LandingPageCustomPage $customPage)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer'
        ]);

        $customPage->update($validated);

        return redirect()->route('landing-page.custom-pages.index')->with('success', __('Custom page updated successfully!'));
    }

    public function destroy(LandingPageCustomPage $customPage)
    {
        $customPage->delete();
        return back()->with('success', __('Custom page deleted successfully!'));
    }

    public function show($slug)
    {
        $page = LandingPageCustomPage::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $landingSettings = \App\Models\LandingPageSetting::getSettings();

        // Get logo from appropriate user based on mode
        $logoUser = null;
        if (config('app.is_saas')) {
            // In SaaS mode, get logo from superadmin
            $logoUser = \App\Models\User::where('type', 'superadmin')->first();
        } else {
            // In non-SaaS mode, get logo from company user with owner role in workspace_member
            $ownerMember = \App\Models\WorkspaceMember::where('role', 'owner')
                ->with('user')
                ->first();
            if ($ownerMember) {
                $logoUser = $ownerMember->user;
            }
        }

        // Get logo settings from the appropriate user
        $logoLight = null;
        $logoDark = null;
        if ($logoUser) {
            $workspaceId = config('app.is_saas') ? null : $logoUser->current_workspace_id;
            $logoLightPath = getSetting('logoLight', null, $logoUser->id, $workspaceId);
            $logoDarkPath = getSetting('logoDark', null, $logoUser->id, $workspaceId);

            // Convert paths to full URLs
            $logoLight = $logoLightPath ? getFile($logoLightPath) : null;
            $logoDark = $logoDarkPath ? getFile($logoDarkPath) : null;
        }

        return Inertia::render('landing-page/custom-page', [
            'page' => $page,
            'customPages' => LandingPageCustomPage::active()->ordered()->get(),
            'settings' => $landingSettings,
            'isSaas' => config('app.is_saas'),
            'logoLight' => $logoLight,
            'logoDark' => $logoDark,
        ]);
    }
}