<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBrandFromDomain
{
    /**
     * Resolve the current brand from the request host and bind it to the container.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $brand = Brand::resolveByAdminHost($host) ?? Brand::resolveByHost($host) ?? Brand::getDefault();
        $request->attributes->set('current_brand', $brand);
        $request->attributes->set('is_admin_domain', Brand::resolveByAdminHost($host) !== null);

        return $next($request);
    }
}
