<?php

namespace App\Listeners;

use App\Notifications\LoginNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class SendLoginNotification implements ShouldQueue
{
    /** Throttle: only one login email per user per 2 minutes (avoids duplicate when Login fires twice). */
    private const THROTTLE_SECONDS = 120;

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user || ! $user->email) {
            return;
        }

        $cacheKey = 'login_notification_sent:'.$user->id;
        if (Cache::has($cacheKey)) {
            return;
        }

        Cache::put($cacheKey, true, self::THROTTLE_SECONDS);
        $user->notify(new LoginNotification(now()->format('d.m.Y H:i')));
    }
}
