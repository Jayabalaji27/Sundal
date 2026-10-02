<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Traits\HasPermissionChecks;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurrencyController extends Controller
{
    use HasPermissionChecks;
    /**
     * Display a listing of currencies.
     */
    public function index(Request $request)
    {
        $this->authorizePermission('currency_view_any');
        
        $query = Currency::query();
        
        // Handle search
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('code', 'like', "%{$searchTerm}%")
                  ->orWhere('symbol', 'like', "%{$searchTerm}%");
            });
        }
        
        // Handle sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 10);
        $currencies = $query->paginate($perPage)->withQueryString();
        
        return Inertia::render('currencies/index', [
            'currencies' => $currencies,
            'filters' => $request->all(['search', 'sort_field', 'sort_direction', 'per_page']),
            'permissions' => [
                'create' => $this->checkPermission('currency_create'),
                'update' => $this->checkPermission('currency_update'),
                'delete' => $this->checkPermission('currency_delete'),
            ],
        ]);
    }

    /**
     * Store a newly created currency.
     */
    public function store(Request $request)
    {
        $this->authorizePermission('currency_create');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies',
            'symbol' => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);
        
        // If this is set as default, unset all other defaults
        if ($request->input('is_default')) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }
        
        Currency::create($validated);
        
        return redirect()->back()->with('success', __('Currency created successfully.'));
    }

    /**
     * Update the specified currency.
     */
    public function update(Request $request, Currency $currency)
    {
        $this->authorizePermission('currency_update');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies,code,' . $currency->id,
            'symbol' => 'required|string|max:10',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);
        
        // If this is set as default, unset all other defaults
        if ($request->input('is_default')) {
            Currency::where('id', '!=', $currency->id)
                  ->where('is_default', true)
                  ->update(['is_default' => false]);
        }
        
        $currency->update($validated);
        
        return redirect()->back()->with('success', __('Currency updated successfully.'));
    }

    /**
     * Remove the specified currency.
     */
    public function destroy(Currency $currency)
    {
        $this->authorizePermission('currency_delete');
        
        // Don't allow deleting the default currency
        if ($currency->is_default) {
            return redirect()->back()->with('error', __('Cannot delete the default currency.'));
        }
        
        $currency->delete();
        
        return redirect()->back()->with('success', __('Currency deleted successfully.'));
    }
    
    /**
     * Get all currencies for settings page.
     */
    public function getAllCurrencies()
    {
        $currencies = Currency::all();
        return response()->json($currencies);
    }
}
