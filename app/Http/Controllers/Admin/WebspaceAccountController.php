<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebspaceAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebspaceAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WebspaceAccount::class);

        $accounts = WebspaceAccount::query()
            ->with(['user:id,name,email', 'hostingPlan:id,name', 'hostingServer:id,hostname'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/webspace-accounts/Index', [
            'webspaceAccounts' => $accounts,
        ]);
    }

    public function show(WebspaceAccount $webspaceAccount): Response
    {
        $this->authorize('view', $webspaceAccount);

        $webspaceAccount->load(['user', 'hostingPlan', 'hostingServer']);

        return Inertia::render('admin/webspace-accounts/Show', [
            'webspaceAccount' => $webspaceAccount,
        ]);
    }
}
