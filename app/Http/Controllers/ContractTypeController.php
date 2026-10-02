<?php

namespace App\Http\Controllers;

use App\Models\ContractType;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractTypeController extends Controller
{
    use HasPermissionChecks;
    
    public function index(Request $request)
    {
        $this->authorizePermission('contract_type_view_any');
        $workspaceId = auth()->user()->current_workspace_id;
        
        // Start with base query
        $query = ContractType::where('workspace_id', $workspaceId)
            ->with('creator');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        // Validate sort fields
        $allowedSortFields = ['name', 'description', 'is_active', 'sort_order'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }
        
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        
        // Apply sorting
        $query->orderBy($sortField, $sortDirection);
        
        // Add contracts count after sorting
        $query->withCount('contracts');

        $perPage = in_array($request->get('per_page', 10), [10, 12, 24, 48, 100]) ? $request->get('per_page', 10) : 10;
        $contractTypes = $query->paginate($perPage);

        return Inertia::render('contracts/types/Index', [
            'contractTypes' => $contractTypes,
            'filters' => $request->only(['status', 'search', 'per_page', 'sort_field', 'sort_direction', 'view_mode']),
            'permissions' => [
                'create' => $this->checkPermission('contract_type_create'),
                'update' => $this->checkPermission('contract_type_update'),
                'delete' => $this->checkPermission('contract_type_delete'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('contract_type_create');
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
        ]);

        $workspaceId = auth()->user()->current_workspace_id;
        ContractType::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => ContractType::forWorkspace($workspaceId)->max('sort_order') + 1,
            'workspace_id' => $workspaceId,
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Contract type created successfully.');
    }

    public function update(Request $request, ContractType $contractType)
    {
        $this->authorizePermission('contract_type_update');
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_active' => 'boolean',
        ]);

        $contractType->update([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', 'Contract type updated successfully.');
    }

    public function destroy(ContractType $contractType)
    {
        $this->authorizePermission('contract_type_delete');
        if ($contractType->contracts()->count() > 0) {
            return redirect()->back()->with('error', 'Cannot delete contract type that has contracts associated with it.');
        }

        $contractType->delete();
        return redirect()->back()->with('success', 'Contract type deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $this->authorizePermission('contract_type_update');
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:contracts_types,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        $workspaceId = auth()->user()->current_workspace_id;
        foreach ($request->items as $item) {
            ContractType::where('id', $item['id'])
                ->where('workspace_id', $workspaceId)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return redirect()->back()->with('success', 'Contract types reordered successfully.');
    }

    public function toggleStatus(ContractType $contractType)
    {
        $this->authorizePermission('contract_type_update');
        $contractType->update(['is_active' => !$contractType->is_active]);
        $status = $contractType->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Contract type {$status} successfully.");
    }
}