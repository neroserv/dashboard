<?php

namespace App\Jobs;

use App\Mail\NewsletterPostMail;
use App\Models\NewsletterPost;
use App\Models\NewsletterSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class SendNewsletterPostJob implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public function __construct(
        public NewsletterPost $post
    ) {}

    public function handle(): void
    {
        $this->post->site->newsletterSubscriptions()
            ->whereNull('unsubscribed_at')
            ->each(function (NewsletterSubscription $subscription): void {
                Mail::to($subscription->email)->send(new NewsletterPostMail($this->post));
            });

        $this->post->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
