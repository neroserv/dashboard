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
        $brand = Brand::resolveByHost($host) ?? Brand::getDefault();
        $request->attributes->set('current_brand', $brand);

        return $next($request);
    }
}
