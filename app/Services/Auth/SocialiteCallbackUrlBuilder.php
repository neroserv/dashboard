<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

/**
 * Builds the OAuth redirect_uri from the current HTTP request so Social login works on every customer domain
 * (multi-brand). Static DISCORD_REDIRECT_URI / APP_URL alone would otherwise pin OAuth to a single host.
 */
final class SocialiteCallbackUrlBuilder
{
    public function absoluteCallbackUrl(Request $request, string $provider): string
    {
        $path = route('auth.social.callback', ['provider' => $provider], false);
        if ($path === '' || $path === '/') {
            return $request->getSchemeAndHttpHost().'/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $request->getSchemeAndHttpHost().$path;
    }
}
