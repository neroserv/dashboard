<?php

namespace App\Services\Monitoring;

use App\Contracts\MonitorChecker;
use App\Models\HostingServer;
use App\Services\ControlPanels\PleskClient;
use App\Services\ControlPanels\PterodactylClient;

class HostingServerApiChecker implements MonitorChecker
{
    public function run(): array
    {
        $results = [];
        $servers = HostingServer::query()->where('is_active', true)->get();

        foreach ($servers as $server) {
            $panelType = $server->getAttribute('panel_type') ?? 'plesk';
            if ($panelType === 'pterodactyl') {
                $client = app(PterodactylClient::class);
                $client->setServer($server);
                $result = $client->testConnection();
            } else {
                $client = app(PleskClient::class);
                $client->setServer($server);
                $result = $client->testConnection();
            }

            $server->update([
                'api_checked_at' => now(),
                'api_check_status' => $result['success'] ? 'ok' : 'error',
                'api_check_message' => $result['message'],
            ]);

            $results[] = [
                'targetName' => $server->name ?: $server->hostname,
                'targetIdentifier' => 'hosting_server:'.$server->id,
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        return $results;
    }
}
