<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    /**
     * Newsletter: formerly site-scoped; now empty (sites product removed).
     */
    public function newsletter(Request $request): Response
    {
        return Inertia::render('modules/Newsletter/Index', [
            'sites' => [],
        ]);
    }

    /**
     * Kontaktformular: formerly site-scoped; now empty (sites product removed).
     */
    public function contact(Request $request): Response
    {
        return Inertia::render('modules/Contact/Index', [
            'sites' => [],
        ]);
    }
}
