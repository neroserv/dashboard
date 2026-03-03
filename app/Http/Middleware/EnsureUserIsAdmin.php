<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Unauthorized.');
        }
        if (! $user->isAdmin() && ! $user->hasPermission('admin.access')) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
