<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\DestroyPushSubscriptionRequest;
use App\Http\Requests\Push\StorePushSubscriptionRequest;
use App\Http\Requests\Push\UpdatePushPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushApiController extends Controller
{
    public function subscribe(StorePushSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $subscription = $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'id' => $subscription->id,
        ]);
    }

    public function unsubscribe(DestroyPushSubscriptionRequest $request): JsonResponse
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->json(['ok' => true]);
    }

    public function preferences(UpdatePushPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $settings = [
            'master_enabled' => $validated['master_enabled'],
            'sync_with_email' => $validated['sync_with_email'],
            'types' => $validated['types'] ?? [],
        ];

        $user->push_settings = $settings;
        $user->save();

        return response()->json(['ok' => true, 'push_settings' => $settings]);
    }

    /**
     * Optional JSON status for clients (VAPID public key, devices).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadCount('pushSubscriptions');

        $subs = $user->pushSubscriptions()
            ->orderByDesc('id')
            ->get(['id', 'endpoint', 'created_at']);

        return response()->json([
            'vapid_public_key' => config('webpush.vapid.public_key'),
            'subscription_count' => $user->push_subscriptions_count,
            'subscriptions' => $subs->map(fn ($s) => [
                'id' => $s->id,
                'endpoint' => $s->endpoint,
                'endpoint_preview' => mb_substr($s->endpoint, 0, 48).'…',
                'created_at' => $s->created_at?->toIso8601String(),
            ]),
            'push_settings' => $user->push_settings ?? [
                'master_enabled' => false,
                'sync_with_email' => true,
                'types' => [],
            ],
        ]);
    }
}
