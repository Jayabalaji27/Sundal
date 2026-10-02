<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $this->resolveAppearance($request));

        return $next($request);
    }

    /**
     * Mirrors the theme-resolution logic in resources/views/app.blade.php:
     * the frontend stores the user's preference in a JSON `themeSettings`
     * cookie (not a plain `appearance` cookie), so it must be parsed here too.
     */
    private function resolveAppearance(Request $request): string
    {
        $cookie = $request->cookie('themeSettings');

        if ($cookie) {
            $data = json_decode($cookie, true);
            if (is_array($data) && in_array($data['appearance'] ?? null, ['light', 'dark', 'system'], true)) {
                return $data['appearance'];
            }
        }

        return getSetting('themeMode', 'light');
    }
}
