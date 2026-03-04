<?php

namespace App\Jobs;

use App\Mail\MonitoringAlertMail;
use App\Models\Setting;
use App\Services\Monitoring\MonitorRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class RunAllMonitoringChecksJob implements ShouldQueue
{
    use Queueable;

    public function handle(MonitorRegistry $registry): void
    {
        $allResults = [];
        foreach ($registry->getCheckers() as $checker) {
            $allResults = array_merge($allResults, $checker->run());
        }

        $failures = array_filter($allResults, fn (array $r) => ! $r['success']);
        if (count($failures) === 0) {
            return;
        }

        $emails = $this->getNotificationEmails();
        if ($emails === []) {
            return;
        }

        Mail::to($emails)->send(new MonitoringAlertMail($failures));
    }

    /**
     * @return list<string>
     */
    private function getNotificationEmails(): array
    {
        $value = Setting::get('monitoring_notification_emails');
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        $default = config('monitoring.notification_emails', []);

        return is_array($default) ? array_values(array_filter($default)) : [];
    }
}
