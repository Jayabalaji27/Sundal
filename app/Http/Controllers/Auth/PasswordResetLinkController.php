<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset link request page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'settings' => settings(),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->with('status', __('A reset link will be sent if the account exists.'));
        }

        try {
            if (isSaasMode()) {
                // An account email from the platform, not from a company: it has to work
                // even for someone whose company hasn't set up its own mail server.
                MailConfigService::usePlatform();
            } else {
                $companyId = $user->created_by ?? $user->id;
                $workspace = $user->currentWorkspace ?? $user->workspaces()->first();
                MailConfigService::setDynamicConfig($companyId, $workspace?->id);
            }

            Password::sendResetLink($request->only('email'));

            return back()->with('status', __('A reset link will be sent if the account exists.'));
        } catch (\Exception $e) {
            \Log::error('Password reset email failed: ' . $e->getMessage());
            return back()->with('error', $e->getMessage());
        }
    }


}
