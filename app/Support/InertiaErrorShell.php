<?php

namespace App\Support;

use Illuminate\Http\Request;

final class InertiaErrorShell
{
    /**
     * Which chrome to use for Inertia error pages: guest (auth card), admin (AdminLayout), app (customer AppLayout).
     */
    public static function forRequest(Request $request): string
    {
        if ($request->user() === null) {
            return 'guest';
        }

        $path = trim($request->path(), '/');

        return preg_match('#^admin($|/)#', $path) ? 'admin' : 'app';
    }
}
