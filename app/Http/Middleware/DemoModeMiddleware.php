<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoModeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('app.is_demo', false)) {
            return $next($request);
        }
        
        // Allow GET requests (viewing data)
        if ($request->isMethod('GET')) {
            return $next($request);
        }
        
        // Allow POST requests for creating new data and demo-enabled approval actions
        if ($request->isMethod('POST') && !$this->isUpdateOrDeleteRoute($request)) {
            return $next($request);
        }
        
        // Block PUT, PATCH, DELETE requests (editing/deleting existing data)
        if (in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
               
            return $this->demoModeResponse($request);
        }

        // Block specific update/delete POST routes
        if ($this->isUpdateOrDeleteRoute($request)) {
            return $this->demoModeResponse($request);
        }

        return $next($request);
    }

    /**
     * Check if the route is for updating or deleting existing data
     */
    private function isUpdateOrDeleteRoute(Request $request): bool
    {
        $route = $request->route();
        if (!$route) return false;

        $routeName = $route->getName();
        $uri = $request->getPathInfo();

        // Allow approval actions for expense and timesheet approvals in demo mode
        $allowedDemoRoutes = [
            'expense-approvals.approve',
            'expense-approvals.reject', 
            'expense-approvals.request-info',
            'expense-approvals.bulk-approve',
            
            'timesheet-approvals.approve',
            'timesheet-approvals.reject',
            'timesheet-approvals.bulk-approve',
            'timesheet-approvals.bulk-reject'
        ];
        
        if ($routeName && in_array($routeName, $allowedDemoRoutes)) {
            return false; // Allow these routes in demo mode
        }

        // Routes that modify existing data
        $restrictedPatterns = [
            '/toggle-status',
            '/approve',
            '/reject',
            '/reset-password',
            '/upgrade-plan',
            '/settings',
            '/update',
            '/destroy',
            '/payment-settings',
            'api/media/batch',
            '/send-mail',
            '/email',
            '/invitations',
            '/language/save',
            '/languages/create',
            '/languages/',
            '/toggle',
            '/zoom-meetings',
        ];

        foreach ($restrictedPatterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return true;
            }
        }

        // Route names that modify existing data
        $restrictedRoutePatterns = [
            '.update',
            '.destroy', 
            '.toggle-status',
            '.approve',
            '.reject',
            '.reset-password',
            '.upgrade-plan',
            'payment.settings',
            'api.media.batch',
            'api.media.destroy',
            '.email',
            'workspace.invitations.store',
            'invitations.resend',
            '.invite-client',
            '.invite-member',
        ];
        
        // Check specific language routes
        $languageRoutes = [
            'language.save',
            'languages.create',
            'languages.delete', 
            'languages.toggle'
        ];
        
        if ($routeName && in_array($routeName, $languageRoutes)) {
            return true;
        }
        


        if ($routeName) {
            foreach ($restrictedRoutePatterns as $pattern) {
                if (str_contains($routeName, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return demo mode response
     */
    private function demoModeResponse(Request $request): Response
    {
        $uri = $request->getPathInfo();
        $routeName = $request->route() ? $request->route()->getName() : '';
        
        // Specific message for email/invitation actions
        if (str_contains($uri, '/email') || str_contains($uri, '/invitations') || str_contains($uri, '/send-mail') ||
            str_contains($routeName, 'email') || str_contains($routeName, 'invitation') || str_contains($routeName, 'invite')) {
            $message = 'Email and invitation functionality is disabled in demo mode to prevent spam.';
        } else {
            $message = 'This action is disabled in demo mode. You can only create new data, not modify existing demo data.';
        }
     
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'demo_mode' => true
            ], 403);
        }

        return redirect()->back()->with('error', $message);
    }
}