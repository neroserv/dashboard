<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * If the authenticated user is a customer (not admin) and their brand does not match
     * the current domain's brand, logout and redirect to login with an error.
     */
    /**
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();
        $currentBrand = $request->attributes->get('current_brand');

        if ($user && ! $user->isAdmin() && $currentBrand !== null) {
            $userBrandId = $user->brand_id;
            if ($userBrandId !== $currentBrand->id) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', __('Dieses Konto ist für eine andere Marke (Portal) vorgesehen. Bitte nutzen Sie die passende Domain.'));
            }
        }

        $isAdminDomain = $request->attributes->get('is_admin_domain', false);
        if ($isAdminDomain && $user) {
            if ($user->isAdmin() || $user->hasPermission('admin.access')) {
                return $request->wantsJson()
                    ? response()->json(['two_factor' => false])
                    : redirect()->route('admin.dashboard');
            }
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', __('Nur Benutzer mit Admin-Berechtigung können sich hier anmelden.'));
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(Fortify::redirects('login'));
    }
}
