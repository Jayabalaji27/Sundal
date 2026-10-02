<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'settings' => settings(),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $this->logLoginHistory($request);

        // Check if email verification is enabled and user is not verified
        $emailVerificationEnabled = getSetting('emailVerification', false);
        if ($emailVerificationEnabled && !$request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }
        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function logLoginHistory(Request $request): void
    {
        $ip = $request->ip();
        // Skip geolocation lookup — use local data only to avoid external API calls
        $locationData = [
            'country'     => 'Local',
            'city'        => 'Localhost',
            'timezone'    => config('app.timezone', 'UTC'),
            'status'      => 'success',
        ];
        $userAgent = $request->userAgent();
        $browserData = parseBrowserData($userAgent);
        $details = array_merge($locationData, $browserData, [
            'status' => 'success',
            'referrer_host' => $request->headers->get('referer') ? parse_url($request->headers->get('referer'), PHP_URL_HOST) : null,
            'referrer_path' => $request->headers->get('referer') ? parse_url($request->headers->get('referer'), PHP_URL_PATH) : null,
        ]);
        
        $loginHistory = new LoginHistory();
        $loginHistory->user_id = Auth::id();
        $loginHistory->ip = $ip;
        $loginHistory->date = now()->toDateString();
        $loginHistory->details = $details;
        $loginHistory->type = Auth::user()->getRoleNames()->first() ?? Auth::user()->type;
        $loginHistory->created_by = Auth::id();
        $loginHistory->save();
    }


    private function getRealIpAddress(Request $request): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to request IP
        $requestIp = $request->ip();
        
        // If it's localhost/development, use a sample IP for testing
        if (in_array($requestIp, ['127.0.0.1', '::1', 'localhost']) || 
            filter_var($requestIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            // Use a real public IP for testing location services
            return '203.0.113.1'; // Documentation IP that should work with geo services
        }
        
        return $requestIp;
    }

    private function getLocationData(string $ip): array
    {
        // For development/local IPs, return mock data
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || 
            !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [
                'country' => 'Local Development',
                'countryCode' => 'DEV',
                'region' => 'DEV',
                'regionName' => 'Development Environment',
                'city' => 'Localhost',
                'zip' => '00000',
                'lat' => 0,
                'lon' => 0,
                'timezone' => 'UTC',
                'isp' => 'Local ISP',
                'org' => 'Development Organization',
                'as' => 'AS0000 Local Network',
                'query' => $ip,
                'status' => 'success'
            ];
        }
        
        try {
            $response = Http::timeout(10)->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query");
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['country'] ?? 'Unknown',
                        'countryCode' => $data['countryCode'] ?? null,
                        'region' => $data['region'] ?? null,
                        'regionName' => $data['regionName'] ?? 'Unknown',
                        'city' => $data['city'] ?? 'Unknown',
                        'zip' => $data['zip'] ?? null,
                        'lat' => $data['lat'] ?? null,
                        'lon' => $data['lon'] ?? null,
                        'timezone' => $data['timezone'] ?? null,
                        'isp' => $data['isp'] ?? null,
                        'org' => $data['org'] ?? null,
                        'as' => $data['as'] ?? null,
                        'query' => $data['query'] ?? $ip,
                        'status' => 'success'
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get location data for IP: ' . $ip . ' - ' . $e->getMessage());
        }
        
        return [
            'country' => 'Unknown',
            'countryCode' => null,
            'region' => null,
            'regionName' => 'Unknown',
            'city' => 'Unknown',
            'zip' => null,
            'lat' => null,
            'lon' => null,
            'timezone' => null,
            'isp' => null,
            'org' => null,
            'as' => null,
            'query' => $ip,
            'status' => 'fail'
        ];
    }


}
