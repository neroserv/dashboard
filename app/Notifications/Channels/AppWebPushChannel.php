<?php

namespace App\Notifications\Channels;

use App\Services\Notifications\WebPushMessageFactory;
use Illuminate\Notifications\Notification;
use Minishlink\WebPush\Subscription;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessageInterface;

final class AppWebPushChannel extends WebPushChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $subscriptions = $notifiable->routeNotificationFor('WebPush', $notification);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $message = $this->resolveWebPushMessage($notifiable, $notification);
        $payload = json_encode($message->toArray());
        $options = $message->getOptions();

        foreach ($subscriptions as $subscription) {
            $this->webPush->queueNotification(new Subscription(
                $subscription->endpoint,
                $subscription->public_key,
                $subscription->auth_token,
                $subscription->content_encoding
            ), $payload, $options);
        }

        $reports = $this->webPush->flush();

        $this->handleReports($reports, $subscriptions, $message);
    }

    private function resolveWebPushMessage(object $notifiable, Notification $notification): WebPushMessageInterface
    {
        if (method_exists($notification, 'toWebPush')) {
            return $notification->toWebPush($notifiable, $notification);
        }

        return WebPushMessageFactory::fromTransactionalMail($notifiable, $notification);
    }
}
