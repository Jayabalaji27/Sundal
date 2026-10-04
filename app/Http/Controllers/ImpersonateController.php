<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Impersonate;

class ImpersonateController extends Controller
{
    public function start(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $originalUserId = auth()->id();

        // Guard against nested impersonation clobbering the original identity:
        // if already impersonating (impersonated_by already set), preserve the
        // FIRST original user, don't overwrite it with the current (impersonated)
        // user's id.
        if (session()->has('impersonated_by')) {
            $originalUserId = session('impersonated_by');
        }

        // Authorize against the original identity (not the impersonated one),
        // using the rules on User: only Super Admin may impersonate, and only
        // company accounts may be impersonated.
        $impersonator = User::find($originalUserId);
        abort_unless($impersonator && $impersonator->canImpersonate(), 403);
        abort_unless($user->canBeImpersonated(), 403);

        if ($user->status !== 'active') {
            return redirect()->route('companies.index')
                ->with('error', __('This company is suspended. Reactivate it before logging in as the company.'));
        }

        Log::info('Impersonation started', [
            'session_id' => $request->session()->getId(),
            'original_user_id' => $originalUserId,
            'target_user_id' => $userId,
            'was_already_impersonating' => session()->has('impersonated_by'),
        ]);

        // Login as the target user first
        auth()->loginUsingId($userId);
        // Then store original user ID in session
        session()->put('impersonated_user_id', $userId);
        session()->put('impersonated_by', $originalUserId);
        session()->save();

        return redirect('/dashboard')->with('success', __('Now impersonating :name', ['name' => $user->name]));
    }

    public function leave(Request $request)
    {
        $originalUserId = session('impersonated_by');

        Log::info('Impersonation leave requested', [
            'session_id' => $request->session()->getId(),
            'current_auth_id' => auth()->id(),
            'current_auth_type' => auth()->user()?->type,
            'session_impersonated_by' => $originalUserId,
        ]);

        if ($originalUserId) {
            auth()->loginUsingId($originalUserId);
            session()->forget('impersonated_by');
            session()->forget('impersonated_user_id');
            session()->save();

            Log::info('Impersonation ended, identity restored', [
                'session_id' => $request->session()->getId(),
                'restored_auth_id' => auth()->id(),
                'restored_auth_type' => auth()->user()?->type,
            ]);
        } else {
            Log::warning('Impersonation leave called but no impersonated_by in session - identity NOT restored', [
                'session_id' => $request->session()->getId(),
                'current_auth_id' => auth()->id(),
            ]);
        }

        return redirect('/companies')->with('success', __('Returned to admin panel'));
    }
}