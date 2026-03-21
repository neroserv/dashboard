<?php

namespace App\Services\Notifications;

use App\Services\Pwa\BrandMediaUrl;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushMessageInterface;

final class WebPushMessageFactory
{
    /**
     * Build a Web Push payload from {@see Notification::toTransactionalMail()} when present.
     */
    public static function fromTransactionalMail(object $notifiable, Notification $notification): WebPushMessageInterface
    {
        if (! method_exists($notification, 'toTransactionalMail')) {
            return (new WebPushMessage)
                ->title(config('app.name', 'Benachrichtigung'))
                ->body('')
                ->icon(BrandMediaUrl::primaryLogoAbsolute(method_exists($notifiable, 'brand') ? $notifiable->brand : null) ?? url('/favicon.svg'))
                ->data(['url' => url('/dashboard')])
                ->options(['TTL' => 86400]);
        }

        $data = $notification->toTransactionalMail($notifiable);
        $content = $data['content'] ?? [];
        $subject = (string) ($content['subject'] ?? '');
        $body = (string) ($content['body'] ?? '');
        $body = (string) preg_replace('/\*\*(.*?)\*\*/s', '$1', $body);
        $body = strip_tags($body);
        $body = trim(preg_replace("/\s+/u", ' ', $body) ?? '');
        if (mb_strlen($body) > 280) {
            $body = mb_substr($body, 0, 277).'...';
        }

        $actionUrl = $data['actionUrl'] ?? null;
        $url = is_string($actionUrl) && $actionUrl !== '' ? $actionUrl : url('/dashboard');

        $brand = null;
        if (method_exists($notifiable, 'brand')) {
            $brand = $notifiable->brand;
        }
        $icon = BrandMediaUrl::primaryLogoAbsolute($brand) ?? url('/favicon.svg');

        return (new WebPushMessage)
            ->title($subject !== '' ? $subject : config('app.name', 'Benachrichtigung'))
            ->body($body !== '' ? $body : '')
            ->icon($icon)
            ->data(['url' => $url])
            ->options(['TTL' => 86400]);
    }
}
