<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        // Check if user is allowed to login
        $user = Auth::user();
        if (!$this->canUserLogin($user)) {
            Auth::logout();
            
            throw ValidationException::withMessages([
                'email' => __('Only company users can log in directly. Please use an invitation link to access the workspace.'),
            ]);
        }
        
        // Check if user login is enabled
        if (!$user->is_enable_login || $user->status !== 'active') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('Your account has been disabled. Please contact administrator.'),
            ]);
        }

        // Deactivated from their workspace (seat freed) — blocked, but their
        // account and all their data (tasks, comments, timesheets...) remain intact.
        if ($user->deactivated_at !== null) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('Your account has been deactivated. Please contact your workspace owner.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Check if user can login directly
     */
    private function canUserLogin($user): bool
    {
        // Allow superadmin and company users to login
        if (in_array($user->type, ['superadmin', 'company'])) {
            return true;
        }

        // Allow staff/member users who belong to a company (created_by is set)
        if ($user->created_by) {
            return true;
        }

        return false;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
