<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBrandMatchesDomain
{
    /**
     * Ensure non-admin users are only allowed when their brand matches the current domain.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $currentBrand = $request->attributes->get('current_brand');

        if ($user && ! $user->isAdmin() && $currentBrand !== null) {
            if ($user->brand_id !== $currentBrand->id) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', __('Dieses Konto ist für eine andere Marke (Portal) vorgesehen. Bitte nutzen Sie die passende Domain.'));
            }
        }

        return $next($request);
    }
}
