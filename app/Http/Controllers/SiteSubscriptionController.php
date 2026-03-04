<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mollie\Api\Exceptions\ApiException as MollieApiException;
use Mollie\Api\MollieApiClient;

class SiteSubscriptionController extends Controller
{
    /**
     * Cancel the site's subscription (user must own the site).
     */
    public function cancel(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        if ($site->user_id !== $request->user()->id) {
            abort(403, 'Nur der Besitzer der Site kann das Abo kündigen.');
        }

        $site->load('siteSubscription');
        $subscription = $site->siteSubscription;

        if (! $subscription || ! $subscription->mollie_subscription_id) {
            return redirect()
                ->route('sites.show', $site)
                ->with('error', 'Kein Abo mit dieser Site verknüpft.');
        }

        $user = $request->user();
        if (! $user->mollie_customer_id) {
            return redirect()
                ->route('sites.show', $site)
                ->with('error', 'Kein Mollie-Kunde verknüpft.');
        }

        try {
            app(MollieApiClient::class)->subscriptions->cancelForId($user->mollie_customer_id, $subscription->mollie_subscription_id);
        } catch (MollieApiException $e) {
            return redirect()
                ->route('sites.show', $site)
                ->with('error', 'Die Kündigung konnte nicht durchgeführt werden. Bitte versuchen Sie es später erneut.');
        }

        $subscription->update(['cancel_at_period_end' => true, 'mollie_status' => 'canceled']);

        return redirect()
            ->route('sites.show', $site)
            ->with('success', 'Ihr Abo wurde gekündigt.');
    }
}
