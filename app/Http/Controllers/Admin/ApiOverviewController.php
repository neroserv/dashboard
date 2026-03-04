<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ApiOverviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ApiOverview', [
            'apiBaseUrl' => url('/api/v1'),
        ]);
    }

    public function docs(): Response
    {
        return Inertia::render('admin/ApiDocs', [
            'apiBaseUrl' => url('/api/v1'),
        ]);
    }
}
