<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonitoringAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{targetName: string, targetIdentifier: string, success: bool, message: string}>  $failures
     */
    public function __construct(
        public array $failures
    ) {}

    public function envelope(): Envelope
    {
        $count = count($this->failures);

        return new Envelope(
            subject: '[Monitoring] '.$count.' Dienst(e) nicht erreichbar',
        );
    }

    public function content(): Content
    {
        $lines = [];
        foreach ($this->failures as $f) {
            $lines[] = '- '.e($f['targetName']).': '.e($f['message']);
        }
        $body = "Folgende Ziele sind nicht erreichbar:\n\n".implode("\n", $lines);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
        $html .= '<h2>Monitoring: Dienst(e) nicht erreichbar</h2>';
        $html .= '<ul>';
        foreach ($this->failures as $f) {
            $html .= '<li><strong>'.e($f['targetName']).'</strong>: '.e($f['message']).'</li>';
        }
        $html .= '</ul></body></html>';

        return new Content(
            htmlString: $html,
        );
    }
}
