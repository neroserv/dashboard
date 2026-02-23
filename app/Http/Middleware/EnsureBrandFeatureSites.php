<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBrandFeatureSites
{
    /**
     * Redirect to dashboard when the current brand does not have the sites_editor feature.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $brand = $request->attributes->get('current_brand');
        if ($brand !== null && ! $brand->getFeaturesArray()['sites_editor']) {
            return redirect()->route('dashboard')
                ->with('info', __('Der Webseiten-Editor ist in diesem Portal nicht verfügbar.'));
        }

        return $next($request);
    }
}
