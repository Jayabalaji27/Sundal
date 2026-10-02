<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Workspace;

class CurrencySettingController extends Controller
{
    /**
     * Update the currency settings.
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'decimalFormat' => 'required|string|in:0,1,2,3,4',
                'defaultCurrency' => 'required|string|exists:currencies,code',
                'decimalSeparator' => 'required|string|in:".",","',
                'thousandsSeparator' => 'required|string',
                'floatNumber' => 'required|boolean',
                'currencySymbolSpace' => 'required|boolean',
                'currencySymbolPosition' => 'required|string|in:before,after',
            ]);
            
            $user = auth()->user();
            $workspaceId = null;
            
            if ($user->type === 'company') {
                $workspaceId = $user->current_workspace_id;
            }
            
            // Update settings using helper function
            foreach ($validated as $key => $value) { 
                updateSetting($key, $value, $user->id, $workspaceId);
            }
            
            return redirect()->back()->with('success', __('Currency settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update currency settings: :error', ['error' => $e->getMessage()]));
        }
    }
}