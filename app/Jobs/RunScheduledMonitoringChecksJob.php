<?php

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunScheduledMonitoringChecksJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $enabled = filter_var(Setting::get('monitoring_enabled', config('monitoring.enabled', true)), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            return;
        }

        $intervalMinutes = (int) (Setting::get('monitoring_check_interval_minutes') ?: config('monitoring.check_interval_minutes', 5));
        if ($intervalMinutes < 1) {
            return;
        }

        $lastRun = Cache::get('monitoring_last_run_at');
        $now = now();
        if ($lastRun !== null) {
            $nextDue = \Carbon\Carbon::parse($lastRun)->addMinutes($intervalMinutes);
            if ($now->lt($nextDue)) {
                return;
            }
        }

        Cache::put('monitoring_last_run_at', $now->toIso8601String(), now()->addDays(1));
        RunAllMonitoringChecksJob::dispatch();
    }
}
