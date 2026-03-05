<?php

namespace App\Jobs;

use App\Models\CronDailyStats;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use romanzipp\QueueMonitor\Traits\IsMonitored;

class CloseInactiveTicketsJob implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    public function __construct(
        public int $resolvedDaysThreshold = 7
    ) {}

    public function handle(): void
    {
        $cutoff = Carbon::now()->subDays($this->resolvedDaysThreshold);

        $tickets = Ticket::query()
            ->where('status', 'resolved')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'closed']);
        }

        if ($tickets->isNotEmpty()) {
            CronDailyStats::incrementMetric('tickets_closed', $tickets->count());
        }
    }
}
