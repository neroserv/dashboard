<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HostingServer;
use App\Models\WebspaceAccount;
use App\Services\ControlPanels\WebspacePanelDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Mollie\Api\Exceptions\ApiException as MollieApiException;
use Mollie\Api\MollieApiClient;

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

    /**
     * Retry webspace panel provisioning (Plesk or KeyHelp, from plan panel_type).
     * Uses existing account data; picks an active server if none set. For pending/active retry cases.
     */
    public function retryPlesk(Request $request, WebspaceAccount $webspaceAccount): RedirectResponse
    {
        $this->authorize('update', $webspaceAccount);

        $plan = $webspaceAccount->hostingPlan;
        if (! $plan) {
            return redirect()->back()->with('error', 'Kein Hosting-Plan zugeordnet.');
        }

        $panelType = $plan->getAttribute('panel_type') ?? 'plesk';
        $server = $webspaceAccount->hostingServer;
        if ($server && ($server->getAttribute('panel_type') ?? 'plesk') !== $panelType) {
            $server = null;
        }
        $server = $server ?? HostingServer::resolveActiveForWebspacePlan($plan);
        if (! $server) {
            return redirect()->back()->with('error', 'Kein aktiver Hosting-Server vorhanden. Bitte unter Admin → Hosting-Server einen Server anlegen und aktivieren.');
        }

        if (! $webspaceAccount->hosting_server_id) {
            $webspaceAccount->update(['hosting_server_id' => $server->id]);
        }

        $password = Str::password(20);
        $pleskUsername = 'ws'.str_pad((string) $webspaceAccount->id, 4, '0', STR_PAD_LEFT).Str::lower(Str::random(6));
        $webspaceAccount->update(['plesk_username' => $pleskUsername]);

        $dispatcher = app(WebspacePanelDispatcher::class);
        $ok = false;

        try {
            $ok = $dispatcher->provisionWebspaceAccount(
                $webspaceAccount,
                $server,
                $plan,
                $pleskUsername,
                $webspaceAccount->domain,
                $password,
                $webspaceAccount->user?->email
            );
        } catch (\Throwable $e) {
            Log::error('Admin retry webspace panel: exception', [
                'webspace_account_id' => $webspaceAccount->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Panel-Fehler: '.$e->getMessage());
        }

        if (! $ok) {
            return redirect()->back()->with('error', 'Der Account konnte auf dem Panel nicht angelegt werden.');
        }

        $webspaceAccount->update([
            'status' => 'active',
            'plesk_password_encrypted' => Crypt::encryptString($password),
        ]);
        Log::info('Admin retry webspace panel: account created', ['webspace_account_id' => $webspaceAccount->id]);

        return redirect()->back()->with('success', 'Hosting-Account wurde angelegt. Der Kunde kann sich nun anmelden.');
    }

    /**
     * Cancel Mollie subscription at period end (admin).
     */
    public function cancelSubscription(Request $request, WebspaceAccount $webspaceAccount): RedirectResponse
    {
        $this->authorize('update', $webspaceAccount);

        if (! $webspaceAccount->mollie_subscription_id) {
            return redirect()
                ->back()
                ->with('error', 'Kein Abo mit diesem Webspace-Account verknüpft.');
        }

        $user = $webspaceAccount->user;
        if (! $user || ! $user->mollie_customer_id) {
            return redirect()
                ->back()
                ->with('error', 'Kein Mollie-Kunde verknüpft.');
        }

        try {
            app(MollieApiClient::class)->subscriptions->cancelForId($user->mollie_customer_id, $webspaceAccount->mollie_subscription_id);
        } catch (MollieApiException $e) {
            return redirect()
                ->back()
                ->with('error', 'Die Kündigung konnte nicht durchgeführt werden: '.$e->getMessage());
        }

        $webspaceAccount->update(['cancel_at_period_end' => true]);

        return redirect()
            ->back()
            ->with('success', 'Webspace-Abo wurde zum Periodenende gekündigt.');
    }
}
