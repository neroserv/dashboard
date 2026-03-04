<?php

namespace App\Services\Monitoring;

use App\Contracts\MonitorChecker;
use App\Models\MonitorTarget;
use Illuminate\Support\Facades\Http;

class HttpUrlChecker implements MonitorChecker
{
    public function run(): array
    {
        $results = [];
        $targets = MonitorTarget::query()
            ->where('type', MonitorTarget::TYPE_HTTP_URL)
            ->where('is_enabled', true)
            ->get();

        foreach ($targets as $target) {
            $url = $target->config['url'] ?? null;
            if (! $url || ! is_string($url)) {
                $results[] = [
                    'targetName' => $target->name,
                    'targetIdentifier' => 'monitor_target:'.$target->id,
                    'success' => false,
                    'message' => 'URL nicht konfiguriert',
                ];

                continue;
            }

            $timeout = (int) ($target->config['timeout'] ?? 10);
            $expectedStatus = (int) ($target->config['expected_status'] ?? 200);

            try {
                $response = Http::timeout($timeout)->get($url);
                $ok = $response->successful() && $response->status() === $expectedStatus;
                $results[] = [
                    'targetName' => $target->name,
                    'targetIdentifier' => 'monitor_target:'.$target->id,
                    'success' => $ok,
                    'message' => $ok ? 'Erreichbar ('.$response->status().')' : 'Unerwarteter Status: '.$response->status().' (erwartet: '.$expectedStatus.')',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'targetName' => $target->name,
                    'targetIdentifier' => 'monitor_target:'.$target->id,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
