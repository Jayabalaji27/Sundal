<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareGlobalSettings;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\DemoModeMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'themeSettings', 'sidebarSettings', 'layoutPosition']);

        $middleware->web(append: [
            CheckInstallation::class,
            HandleAppearance::class,
            ShareGlobalSettings::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            DemoModeMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'permissions' => \App\Http\Middleware\CheckMultiplePermissions::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'landing.enabled' => \App\Http\Middleware\CheckLandingPageEnabled::class,
            'verified' => App\Http\Middleware\EnsureEmailIsVerified::class,
            'plan.access' => \App\Http\Middleware\CheckPlanAccess::class,
            'plan.limits' => \App\Http\Middleware\CheckPlanLimits::class,
            'module.access' => \App\Http\Middleware\CheckModuleAccess::class,
            'ai.mode' => \App\Http\Middleware\EnsureAiModeSession::class,
            'ai.assistant' => \App\Http\Middleware\EnsureAiAssistantAccess::class,
            'saas.only' => \App\Http\Middleware\SaasOnly::class,
            'block.client.profile' => \App\Http\Middleware\BlockClientProfileAccess::class,
            'block.superadmin.workspace' => \App\Http\Middleware\BlockSuperAdminWorkspaceAccess::class,
        ]);

        $middleware->validateCsrfTokens(
        except: [
            'install/*',
            'update/*',
            'f/*',
            'cashfree/create-session', 
            'cashfree/webhook',
            'ozow/create-payment',
            'payments/easebuzz/success',
            'payments/aamarpay/success',
            'payments/aamarpay/callback',
            'payments/tap/success',
            'payments/tap/callback',
            'payments/benefit/success',
            'payments/benefit/callback',
            'payments/paytabs/callback',
            'payments/iyzipay/success',
            'payments/iyzipay/callback',
            // Gateway webhooks - verified in their controllers, not by session.
            'payments/payhere/callback',
            'payments/sspay/callback',
            'payments/yookassa/callback',
            'payments/easebuzz/callback',
            'api/media/batch'
            ],
        );

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Laravel's default guest redirect stores any unauthenticated GET as
        // url.intended. A background fetch() polling after logout (e.g. the
        // timer's /timer/status) then becomes the next user's post-login page.
        // Only remember real page loads (Sec-Fetch-Dest: document) and Inertia
        // visits; other fetches just go to login. Without Sec-Fetch headers
        // the default behaviour applies.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            $dest = $request->header('Sec-Fetch-Dest');

            if ($request->expectsJson() || $dest === null || $dest === 'document' || $request->header('X-Inertia')) {
                return null;
            }

            return redirect()->to($e->redirectTo($request) ?? route('login'));
        });

        // Without this, a 403/404/419/500 response to an Inertia XHR request
        // (e.g. a stale double-submit hitting an already-deleted model, or a
        // permission-gate abort() on a unified-shell redirect route) comes back
        // as a plain non-Inertia error page. Inertia's client can't render that
        // inside the SPA and falls back to its built-in "unexpected response"
        // overlay / a full page reload, which reads as a false error even when
        // the underlying action already succeeded.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            // As a validation-style error, not a plain redirect: Inertia treats a
            // redirect as success, so forms cleared themselves and showed their
            // success toast although nothing was saved (QA CI1/CI2).
            if ($response->getStatusCode() === 419) {
                return back()->withErrors([
                    'error' => 'The page expired, please try again.',
                ]);
            }

            // Same reason for a 403 on an Inertia form submit: rendering the error
            // page counts as a successful visit, so onSuccess showed e.g. "Manager
            // added to project" although the action was refused. Send it back as
            // an error instead, so onError runs and the user stays on the page.
            if ($response->getStatusCode() === 403 && $request->header('X-Inertia') && ! $request->isMethod('GET')) {
                $message = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : __('You do not have permission to perform this action.');

                return back()->withErrors(['error' => $message]);
            }

            // 403/404 are deliberate authorization/routing outcomes, not unexpected
            // crashes — a raw stack trace has no debugging value for them, so show
            // the friendly page in every environment, local/testing included.
            if (in_array($response->getStatusCode(), [403, 404], true)) {
                return Inertia::render('errors/error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503], true)) {
                return Inertia::render('errors/error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
