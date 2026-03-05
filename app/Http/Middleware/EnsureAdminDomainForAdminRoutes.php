<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminDomainForAdminRoutes
{
    /**
     * Admin routes (/admin) are only accessible when the request host is an admin domain.
     * Prevents accessing the admin panel by simply typing /admin on the customer domain.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request);
        }

        if (! $request->attributes->get('is_admin_domain', false)) {
            if ($request->user()) {
                return redirect()->route('dashboard')
                    ->with('error', __('Der Admin-Bereich ist nur über die konfigurierte Admin-URL erreichbar.'));
            }

            return redirect()->route('login')
                ->with('error', __('Der Admin-Bereich ist nur über die konfigurierte Admin-URL erreichbar.'));
        }

        return $next($request);
    }
}
