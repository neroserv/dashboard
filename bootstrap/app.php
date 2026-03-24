<?php

use App\Http\Middleware\EnsureAdminDomainForAdminRoutes;
use App\Http\Middleware\EnsureBillingProfile;
use App\Http\Middleware\EnsureNotInMaintenanceMode;
use App\Http\Middleware\EnsureUserBrandMatchesDomain;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveBrandFromDomain;
use App\Support\InertiaErrorShell;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Caddy als vertrauenswürdigen Proxy konfigurieren
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'cookie_consent']);

        $middleware->validateCsrfTokens(except: [
            'api/verify-domain', // Caddy On-Demand TLS verification (GET, no session)
            'webhooks/mollie',
            'webhooks/mollie/*',
            'webhooks/discord/interactions',
        ]);

        $middleware->prepend(ResolveBrandFromDomain::class);

        $middleware->prependToGroup('web', EnsureNotInMaintenanceMode::class);
        $middleware->prependToGroup('api', EnsureNotInMaintenanceMode::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.domain' => EnsureAdminDomainForAdminRoutes::class,
            'billing.profile' => EnsureBillingProfile::class,
            'brand.domain' => EnsureUserBrandMatchesDomain::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $rawMessage = $e->getMessage();
                $message = ($rawMessage !== '' && $rawMessage !== (string) $status) ? $rawMessage : null;
            } elseif (! config('app.debug')) {
                $status = 500;
                $message = null;
            } else {
                return null;
            }

            if ($status < 400 || $status > 599) {
                return null;
            }

            $titles = [
                401 => 'Nicht angemeldet',
                403 => 'Zugriff verweigert',
                404 => 'Seite nicht gefunden',
                405 => 'Methode nicht erlaubt',
                408 => 'Zeitüberschreitung',
                409 => 'Konflikt',
                410 => 'Nicht mehr verfügbar',
                419 => 'Sitzung abgelaufen',
                422 => 'Ungültige Daten',
                429 => 'Zu viele Anfragen',
                500 => 'Serverfehler',
                502 => 'Ungültige Antwort',
                503 => 'Dienst nicht verfügbar',
                504 => 'Gateway-Zeitüberschreitung',
            ];

            return Inertia::render('errors/HttpError', [
                'status' => $status,
                'title' => $titles[$status] ?? 'Fehler',
                'message' => $message,
                'shell' => InertiaErrorShell::forRequest($request),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
