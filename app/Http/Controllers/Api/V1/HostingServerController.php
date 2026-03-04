<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HostingServer;
use App\Services\ControlPanels\PterodactylClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HostingServerController extends ApiV1Controller
{
    /**
     * List hosting servers (details + API status only, no sensitive data).
     */
    public function index(Request $request): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        $brandId = $brand?->id;

        $servers = HostingServer::query()
            ->when($brandId !== null, fn ($q) => $q->where('brand_id', $brandId))
            ->withCount(['webspaceAccounts', 'gameServerAccounts'])
            ->orderBy('name')
            ->get();

        $data = $servers->map(fn (HostingServer $server) => $this->serverToPublicArray($server))->values()->all();

        return response()->json(['data' => $data]);
    }

    /**
     * Single hosting server details + API status. For Pterodactyl: nodes overview (no credentials).
     */
    public function show(Request $request, HostingServer $hostingServer): JsonResponse
    {
        $brand = $this->resolveBrand($request);
        $brandId = $brand?->id;

        if ($brandId !== null && (int) $hostingServer->brand_id !== (int) $brandId) {
            return response()->json(['message' => 'Hosting server not found.'], 404);
        }

        $hostingServer->loadCount(['webspaceAccounts', 'gameServerAccounts']);
        $payload = $this->serverToPublicArray($hostingServer);

        $panelType = $hostingServer->getAttribute('panel_type') ?? 'plesk';
        if ($panelType === 'pterodactyl') {
            try {
                $client = app(PterodactylClient::class);
                $client->setServer($hostingServer);
                $nodes = $client->getNodesOverview();
                $payload['nodes'] = $nodes ?? [];
            } catch (\Throwable) {
                $payload['nodes'] = [];
            }
        } else {
            $payload['nodes'] = null;
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serverToPublicArray(HostingServer $server): array
    {
        return [
            'id' => $server->id,
            'name' => $server->name,
            'hostname' => $server->hostname,
            'panel_type' => $server->getAttribute('panel_type') ?? 'plesk',
            'port' => $server->port,
            'use_ssl' => (bool) $server->use_ssl,
            'is_active' => (bool) $server->is_active,
            'api_status' => [
                'checked_at' => $server->api_checked_at?->toIso8601String(),
                'status' => $server->api_check_status,
                'message' => $server->api_check_message,
            ],
            'webspace_accounts_count' => $server->webspace_accounts_count ?? 0,
            'game_server_accounts_count' => $server->game_server_accounts_count ?? 0,
        ];
    }
}
