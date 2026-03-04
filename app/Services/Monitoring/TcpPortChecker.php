<?php

namespace App\Services\Monitoring;

use App\Contracts\MonitorChecker;
use App\Models\MonitorTarget;

class TcpPortChecker implements MonitorChecker
{
    public function run(): array
    {
        $results = [];
        $targets = MonitorTarget::query()
            ->where('type', MonitorTarget::TYPE_TCP_PORT)
            ->where('is_enabled', true)
            ->get();

        foreach ($targets as $target) {
            $host = $target->config['host'] ?? null;
            $port = isset($target->config['port']) ? (int) $target->config['port'] : null;
            if (! $host || ! is_string($host) || $port === null || $port < 1 || $port > 65535) {
                $results[] = [
                    'targetName' => $target->name,
                    'targetIdentifier' => 'monitor_target:'.$target->id,
                    'success' => false,
                    'message' => 'Host oder Port nicht gültig konfiguriert',
                ];

                continue;
            }

            $timeout = (int) ($target->config['timeout'] ?? 5);
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
            $ok = $socket !== false;
            if ($socket !== false) {
                fclose($socket);
            }

            $results[] = [
                'targetName' => $target->name,
                'targetIdentifier' => 'monitor_target:'.$target->id,
                'success' => $ok,
                'message' => $ok ? 'Port erreichbar' : ($errstr ?: "Fehler: {$errno}"),
            ];
        }

        return $results;
    }
}
