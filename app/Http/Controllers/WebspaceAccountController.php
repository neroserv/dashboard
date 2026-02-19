<?php

namespace App\Http\Controllers;

use App\Models\WebspaceAccount;
use App\Services\ControlPanels\PleskClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class WebspaceAccountController extends Controller
{
    /**
     * List current user's webspace accounts.
     */
    public function index(Request $request): Response
    {
        $accounts = $request->user()
            ->webspaceAccounts()
            ->with('hostingPlan')
            ->latest()
            ->get();

        return Inertia::render('webspace-accounts/Index', [
            'webspaceAccounts' => $accounts,
        ]);
    }

    /**
     * Show one webspace account (credentials, Plesk/Webmail links). Only owner.
     */
    public function show(Request $request, WebspaceAccount $webspaceAccount): Response|RedirectResponse
    {
        $this->authorize('view', $webspaceAccount);

        $webspaceAccount->load('hostingPlan', 'hostingServer');

        $pleskPassword = null;
        if ($webspaceAccount->plesk_password_encrypted) {
            try {
                $pleskPassword = Crypt::decryptString($webspaceAccount->plesk_password_encrypted);
            } catch (\Throwable) {
                // ignore
            }
        }

        return Inertia::render('webspace-accounts/Show', [
            'webspaceAccount' => $webspaceAccount,
            'pleskPassword' => $pleskPassword,
            'webmailUrl' => 'https://webmail.'.$webspaceAccount->domain,
        ]);
    }

    /**
     * Redirect to Plesk panel with session (create_session API).
     */
    public function pleskLogin(Request $request, WebspaceAccount $webspaceAccount): RedirectResponse
    {
        $this->authorize('view', $webspaceAccount);

        $server = $webspaceAccount->hostingServer;
        if (! $server) {
            return redirect()->back()->with('error', 'Kein Hosting-Server zugeordnet.');
        }

        $plesk = app(PleskClient::class);
        $plesk->setServer($server);
        $clientIp = $request->ip() ?? '127.0.0.1';
        $token = $plesk->createCustomerSession($webspaceAccount->plesk_username, $clientIp);

        if (! $token) {
            return redirect()->back()->with('error', 'Plesk-Login konnte nicht erstellt werden. Bitte versuchen Sie es später erneut.');
        }

        $url = 'https://'.$server->hostname.':8443/enterprise/rsession_init.php?PLESKSESSID='.urlencode($token);

        return redirect()->away($url);
    }
}
