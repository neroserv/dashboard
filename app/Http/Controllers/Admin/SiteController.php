<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteStatusRequest;
use App\Http\Requests\Admin\UpdateSiteSubscriptionRequest;
use App\Models\AdminActivityLog;
use App\Models\Site;
use App\Notifications\SiteSuspendedNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mollie\Api\Exceptions\ApiException as MollieApiException;
use Mollie\Api\MollieApiClient;

class SiteController extends Controller
{
    public function show(Site $site): Response
    {
        $site->load(['template', 'user', 'siteSubscription']);

        $data = array_merge(
            ['id' => $site->id],
            $site->makeHidden('id')->toArray()
        );
        $sub = $site->siteSubscription;
        if ($sub && $sub->current_period_ends_at) {
            $data['site_subscription']['current_period_ends_at_formatted'] = Carbon::parse($sub->current_period_ends_at)->format('d.m.Y');
            $data['site_subscription']['current_period_ends_at_date'] = Carbon::parse($sub->current_period_ends_at)->format('Y-m-d');
        } elseif (! empty($data['site_subscription'])) {
            $data['site_subscription']['current_period_ends_at_formatted'] = null;
            $data['site_subscription']['current_period_ends_at_date'] = null;
        }

        $activityLog = AdminActivityLog::query()
            ->where('model_type', Site::class)
            ->where('model_id', $site->id)
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($log) => array_merge($log->toArray(), [
                'created_at' => $log->created_at->format('d.m.Y H:i'),
            ]));

        return Inertia::render('admin/sites/Show', [
            'site' => $data,
            'activityLog' => $activityLog,
        ]);
    }

    public function index(Request $request): Response
    {
        $query = Site::query()
            ->with(['template', 'user', 'siteSubscription']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('legacy')) {
            $query->where('is_legacy', $request->boolean('legacy'));
        }

        $sites = $query->latest()->paginate(15)->withQueryString()->through(function (Site $site) {
            $arr = $site->makeHidden('id')->toArray();
            if (! empty($arr['site_subscription']['current_period_ends_at'] ?? null)) {
                $arr['site_subscription']['current_period_ends_at'] = Carbon::parse($arr['site_subscription']['current_period_ends_at'])->format('d.m.Y');
            }

            return $arr;
        });

        return Inertia::render('admin/sites/Index', [
            'sites' => $sites,
            'filters' => $request->only(['status', 'legacy']),
        ]);
    }

    public function updateStatus(UpdateSiteStatusRequest $request, Site $site): RedirectResponse
    {
        $previousStatus = $site->status;
        $site->update(['status' => $request->validated('status')]);

        AdminActivityLog::log(
            $request->user()->id,
            'site_status_updated',
            Site::class,
            $site->id,
            ['status' => $previousStatus],
            ['status' => $site->status],
        );

        if ($previousStatus !== 'suspended' && $site->status === 'suspended') {
            $site->user?->notify(new SiteSuspendedNotification($site));
        }

        return redirect()
            ->route('admin.sites.show', $site)
            ->with('success', $site->status === 'suspended' ? 'Site wurde gesperrt.' : 'Site wurde entsperrt.');
    }

    public function updateSubscription(UpdateSiteSubscriptionRequest $request, Site $site): RedirectResponse
    {
        $site->load('siteSubscription');
        $subscription = $site->siteSubscription;

        if (! $subscription) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Abo mit dieser Site verknüpft.');
        }

        $oldEnd = $subscription->current_period_ends_at?->format('Y-m-d');
        $subscription->update([
            'current_period_ends_at' => Carbon::parse($request->validated('current_period_ends_at')),
        ]);

        AdminActivityLog::log(
            $request->user()->id,
            'site_subscription_period_updated',
            Site::class,
            $site->id,
            ['current_period_ends_at' => $oldEnd],
            ['current_period_ends_at' => $request->validated('current_period_ends_at')],
        );

        return redirect()
            ->route('admin.sites.show', $site)
            ->with('success', 'Laufzeitende wurde lokal gespeichert. Mollie wurde nicht geändert.');
    }

    public function cancelSubscription(Site $site): RedirectResponse
    {
        $site->load('siteSubscription');
        $subscription = $site->siteSubscription;

        if (! $subscription || ! $subscription->mollie_subscription_id) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Abo mit dieser Site verknüpft.');
        }

        $user = $site->user;
        if (! $user || ! $user->mollie_customer_id) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Mollie-Kunde verknüpft.');
        }

        try {
            app(MollieApiClient::class)->subscriptions->cancelForId($user->mollie_customer_id, $subscription->mollie_subscription_id);
        } catch (MollieApiException $e) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Die Kündigung konnte nicht durchgeführt werden. Bitte versuchen Sie es später erneut.');
        }

        $subscription->update(['cancel_at_period_end' => true, 'mollie_status' => 'canceled']);

        AdminActivityLog::log(
            request()->user()->id,
            'site_subscription_cancelled',
            Site::class,
            $site->id,
            null,
            ['cancel_at_period_end' => true],
        );

        return redirect()
            ->route('admin.sites.show', $site)
            ->with('success', 'Abo wurde zum Periodenende gekündigt.');
    }

    public function reactivateSubscription(Site $site): RedirectResponse
    {
        $site->load('siteSubscription');
        $subscription = $site->siteSubscription;

        if (! $subscription || ! $subscription->mollie_subscription_id) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Abo mit dieser Site verknüpft.');
        }

        try {
            $mollie = app(MollieApiClient::class);
            $user = $site->user;
            if ($user && $user->mollie_customer_id) {
                $mollieSub = $mollie->subscriptions->getForId($user->mollie_customer_id, $subscription->mollie_subscription_id);
                if ($mollieSub->status === 'canceled') {
                    return redirect()
                        ->route('admin.sites.show', $site)
                        ->with('error', 'Das Abo wurde bereits gekündigt und kann nicht reaktiviert werden.');
                }
            }
        } catch (MollieApiException $e) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Die Reaktivierung konnte nicht durchgeführt werden. Bitte versuchen Sie es später erneut.');
        }

        $subscription->update(['cancel_at_period_end' => false]);

        AdminActivityLog::log(
            request()->user()->id,
            'site_subscription_reactivated',
            Site::class,
            $site->id,
            ['cancel_at_period_end' => true],
            ['cancel_at_period_end' => false],
        );

        return redirect()
            ->route('admin.sites.show', $site)
            ->with('success', 'Kündigung wurde zurückgenommen. Das Abo läuft weiter.');
    }

    /**
     * Manually sync local SiteSubscription with Mollie (one-time read from Mollie, update local).
     */
    public function syncSubscription(Site $site): RedirectResponse
    {
        $site->load('siteSubscription');
        $subscription = $site->siteSubscription;

        if (! $subscription || ! $subscription->mollie_subscription_id) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Abo mit dieser Site verknüpft.');
        }

        $user = $site->user;
        if (! $user || ! $user->mollie_customer_id) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Kein Mollie-Kunde verknüpft.');
        }

        try {
            $mollieSub = app(MollieApiClient::class)->subscriptions->getForId($user->mollie_customer_id, $subscription->mollie_subscription_id);
        } catch (MollieApiException $e) {
            return redirect()
                ->route('admin.sites.show', $site)
                ->with('error', 'Mollie-Abo konnte nicht abgerufen werden. Bitte später erneut versuchen.');
        }

        $currentPeriodEnd = $mollieSub->nextPaymentDate ? Carbon::parse($mollieSub->nextPaymentDate) : null;
        $endsAt = $mollieSub->canceledAt ? Carbon::parse($mollieSub->canceledAt) : null;

        $old = $subscription->only(['mollie_status', 'current_period_ends_at', 'cancel_at_period_end']);
        $subscription->update([
            'mollie_status' => $mollieSub->status ?? $subscription->mollie_status,
            'current_period_ends_at' => $currentPeriodEnd ?? $subscription->current_period_ends_at,
            'cancel_at_period_end' => ($mollieSub->status ?? '') === 'canceled',
            'ends_at' => $endsAt ?? $subscription->ends_at,
        ]);

        AdminActivityLog::log(request()->user()->id, 'site_subscription_synced', Site::class, $site->id, $old, null);

        return redirect()
            ->route('admin.sites.show', $site)
            ->with('success', 'Abo mit Mollie abgeglichen.');
    }
}
