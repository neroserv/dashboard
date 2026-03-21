<?php

namespace App\Services\Notifications;

use App\Models\User;

final class PushPreferenceEvaluator
{
    /**
     * Whether Web Push should be included for this user and notification template key.
     */
    public function shouldIncludeWebPush(User $user, string $notificationType): bool
    {
        $settings = $user->push_settings ?? [];
        if (! ($settings['master_enabled'] ?? false)) {
            return false;
        }

        if ($user->pushSubscriptions()->count() === 0) {
            return false;
        }

        $syncWithEmail = $settings['sync_with_email'] ?? true;
        if ($syncWithEmail) {
            $preference = $user->notification_preferences[$notificationType] ?? 'email';

            return in_array($preference, ['email', 'email_discord'], true);
        }

        $types = $settings['types'] ?? [];

        return (bool) ($types[$notificationType] ?? false);
    }
}
