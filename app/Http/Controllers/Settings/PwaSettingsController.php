<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PwaSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $templates = EmailTemplate::query()->orderBy('key')->get(['key', 'name']);

        $pushSettings = $user->push_settings ?? [
            'master_enabled' => false,
            'sync_with_email' => true,
            'types' => [],
        ];

        $subscriptions = $user->pushSubscriptions()
            ->orderByDesc('id')
            ->get(['id', 'endpoint', 'created_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'endpoint' => $s->endpoint,
                'endpoint_preview' => mb_substr($s->endpoint, 0, 48).'…',
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return Inertia::render('settings/Pwa', [
            'templates' => $templates,
            'pushSettings' => $pushSettings,
            'pushSubscriptions' => $subscriptions,
            'vapidPublicKey' => config('webpush.vapid.public_key'),
        ]);
    }
}
