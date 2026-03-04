<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\HostingPlan;
use App\Models\HostingServer;
use App\Services\ControlPanels\PterodactylClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PterodactylController extends ApiV1Controller
{
    /**
     * List nests for a Pterodactyl plan (for configurator).
     */
    public function nests(Request $request): JsonResponse
    {
        $planId = $request->integer('hosting_plan_id');
        if ($planId < 1) {
            return response()->json(['message' => 'hosting_plan_id is required'], 400);
        }

        $plan = HostingPlan::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->find($planId);
        if (! $plan || ! $plan->hosting_server_id) {
            return response()->json(['data' => ['nests' => []]]);
        }

        $server = HostingServer::query()
            ->where('id', $plan->hosting_server_id)
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->first();
        if (! $server) {
            return response()->json(['data' => ['nests' => []]]);
        }

        try {
            $client = app(PterodactylClient::class);
            $client->setServer($server);
            $nestsRaw = $client->getNests();
            $nests = [];
            foreach ($nestsRaw as $n) {
                $attrs = is_array($n) ? ($n['attributes'] ?? $n) : (array) $n;
                $id = (int) ($attrs['id'] ?? 0);
                $name = (string) ($attrs['name'] ?? 'Nest '.$id);
                if ($id > 0) {
                    $nests[] = ['id' => $id, 'name' => $name];
                }
            }

            return response()->json(['data' => ['nests' => $nests]]);
        } catch (\Throwable) {
            return response()->json(['data' => ['nests' => []]]);
        }
    }

    /**
     * List eggs for a nest (for configurator).
     */
    public function eggs(Request $request): JsonResponse
    {
        $planId = $request->integer('hosting_plan_id');
        $nestId = $request->integer('nest_id');
        if ($planId < 1 || $nestId < 1) {
            return response()->json(['message' => 'hosting_plan_id and nest_id are required'], 400);
        }

        $plan = HostingPlan::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->find($planId);
        if (! $plan || ! $plan->hosting_server_id) {
            return response()->json(['data' => ['eggs' => []]]);
        }

        $server = HostingServer::query()
            ->where('id', $plan->hosting_server_id)
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->first();
        if (! $server) {
            return response()->json(['data' => ['eggs' => []]]);
        }

        try {
            $client = app(PterodactylClient::class);
            $client->setServer($server);
            $eggsRaw = $client->getEggs($nestId);
            $eggs = [];
            foreach ($eggsRaw as $e) {
                $attrs = is_array($e) ? ($e['attributes'] ?? $e) : (array) $e;
                $id = (int) ($attrs['id'] ?? 0);
                $name = (string) ($attrs['name'] ?? 'Egg '.$id);
                if ($id > 0) {
                    $eggs[] = ['id' => $id, 'name' => $name];
                }
            }

            return response()->json(['data' => ['eggs' => $eggs]]);
        } catch (\Throwable) {
            return response()->json(['data' => ['eggs' => []]]);
        }
    }
}
