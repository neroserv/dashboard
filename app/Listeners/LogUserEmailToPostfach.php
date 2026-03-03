<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserEmailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

class LogUserEmailToPostfach
{
    public function handle(MessageSent $event): void
    {
        $symfonyMessage = $event->sent->getOriginalMessage();
        if (! $symfonyMessage instanceof Email) {
            return;
        }

        $toAddresses = $symfonyMessage->getTo();
        $subject = $symfonyMessage->getSubject() ?? '(Kein Betreff)';
        $htmlBody = $this->getHtmlBody($symfonyMessage);
        if ($htmlBody === null || $htmlBody === '') {
            return;
        }

        $snippet = $this->makeSnippet($htmlBody);

        foreach ($toAddresses as $address) {
            $emailString = method_exists($address, 'getAddress') ? $address->getAddress() : (string) $address;
            $user = User::where('email', $emailString)->first();
            if (! $user) {
                continue;
            }

            UserEmailLog::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'body_html' => $htmlBody,
                'snippet' => $snippet,
                'notification_type' => null,
                'sent_at' => now(),
            ]);
        }
    }

    private function getHtmlBody(Email $email): ?string
    {
        $body = $email->getHtmlBody();
        if ($body === null) {
            $body = $email->getTextBody();
        }
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) ? $body : null;
    }

    private function makeSnippet(string $html, int $maxLength = 100): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength).'...';
    }
}
