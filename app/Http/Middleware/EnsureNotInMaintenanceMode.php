<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceService;
use App\Support\InertiaErrorShell;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInMaintenanceMode
{
    public function __construct(
        protected MaintenanceService $maintenance,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->shouldBlockRequest($request)) {
            return $next($request);
        }

        $payload = $this->maintenance->payloadForBlockedRequest($request);

        if ($request->expectsJson() || $request->is('api/*')) {
            $body = [
                'message' => $payload['message'] ?? 'Wir führen derzeit Wartungsarbeiten durch.',
                'until' => $payload['until']?->toIso8601String(),
            ];

            $response = response()->json($body, 503);
            if ($payload['until'] !== null && $payload['until']->isFuture()) {
                $response->headers->set('Retry-After', (string) max(1, (int) ($payload['until']->timestamp - time())));
            }

            return $response;
        }

        $response = Inertia::render('errors/Maintenance', [
            'message' => $payload['message'],
            'until' => $payload['until']?->toIso8601String(),
            'brandName' => $payload['brand_name'],
            'shell' => InertiaErrorShell::forRequest($request),
        ])->toResponse($request);

        $response->setStatusCode(503);
        if ($payload['until'] !== null && $payload['until']->isFuture()) {
            $response->headers->set('Retry-After', (string) max(1, (int) ($payload['until']->timestamp - time())));
        }

        return $response;
    }
}
