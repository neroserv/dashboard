<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameserverCloudPlanRequest;
use App\Http\Requests\Admin\UpdateGameserverCloudPlanRequest;
use App\Models\Brand;
use App\Models\GameserverCloudPlan;
use App\Models\HostingServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameserverCloudPlanController extends Controller
{
    protected function currentBrand(Request $request): ?Brand
    {
        $brand = $request->attributes->get('current_brand');

        return $brand ?? Brand::getDefault();
    }

    protected function ensureGameserverCloudFeature(Request $request): ?RedirectResponse
    {
        $brand = $this->currentBrand($request);
        $features = $brand?->getFeaturesArray() ?? [];
        if (! ($features['gameserver_cloud'] ?? false)) {
            return redirect()->route('admin.settings.index', ['tab' => 'marken'])
                ->with('error', 'Gameserver Cloud ist für diese Marke nicht aktiviert.');
        }

        return null;
    }

    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', GameserverCloudPlan::class);
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        return redirect()->route('admin.hosting-plans.index', ['tab' => 'cloud']);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', GameserverCloudPlan::class);
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $pterodactylServers = HostingServer::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'hostname'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name ?? $s->hostname, 'hostname' => $s->hostname]);

        $availableOptionIdsCloud = [
            ['value' => 'max_memory_mb', 'label' => 'RAM (max_memory_mb)'],
            ['value' => 'max_disk_gb', 'label' => 'Disk (max_disk_gb)'],
        ];

        return Inertia::render('admin/gameserver-cloud-plans/Create', [
            'pterodactylHostingServers' => $pterodactylServers,
            'availableOptionIdsCloud' => $availableOptionIdsCloud,
        ]);
    }

    public function store(StoreGameserverCloudPlanRequest $request): RedirectResponse
    {
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $currentBrand = $this->currentBrand($request);
        $brandId = $currentBrand?->id ?? Brand::query()->value('id');
        $data = array_merge($request->validated(), ['brand_id' => $brandId]);
        GameserverCloudPlan::query()->create($data);

        return redirect()->route('admin.hosting-plans.index', ['tab' => 'cloud'])
            ->with('success', 'Gameserver-Cloud-Plan wurde angelegt.');
    }

    public function edit(Request $request, GameserverCloudPlan $gameserverCloudPlan): Response|RedirectResponse
    {
        $this->authorize('update', $gameserverCloudPlan);
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $gameserverCloudPlan->load('hostingServer');
        $pterodactylServers = HostingServer::query()
            ->where('panel_type', 'pterodactyl')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'hostname'])
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name ?? $s->hostname, 'hostname' => $s->hostname]);

        $availableOptionIdsCloud = [
            ['value' => 'max_memory_mb', 'label' => 'RAM (max_memory_mb)'],
            ['value' => 'max_disk_gb', 'label' => 'Disk (max_disk_gb)'],
        ];

        return Inertia::render('admin/gameserver-cloud-plans/Edit', [
            'gameserverCloudPlan' => $gameserverCloudPlan,
            'pterodactylHostingServers' => $pterodactylServers,
            'availableOptionIdsCloud' => $availableOptionIdsCloud,
        ]);
    }

    public function update(UpdateGameserverCloudPlanRequest $request, GameserverCloudPlan $gameserverCloudPlan): RedirectResponse
    {
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        $gameserverCloudPlan->update($request->validated());

        return redirect()->route('admin.hosting-plans.index', ['tab' => 'cloud'])
            ->with('success', 'Gameserver-Cloud-Plan wurde gespeichert.');
    }

    public function destroy(Request $request, GameserverCloudPlan $gameserverCloudPlan): RedirectResponse
    {
        $this->authorize('delete', $gameserverCloudPlan);
        $redirect = $this->ensureGameserverCloudFeature($request);
        if ($redirect !== null) {
            return $redirect;
        }

        if ($gameserverCloudPlan->gameserverCloudSubscriptions()->exists()) {
            return redirect()->route('admin.hosting-plans.index', ['tab' => 'cloud'])
                ->with('error', 'Der Plan hat noch aktive Abos und kann nicht gelöscht werden.');
        }

        $gameserverCloudPlan->delete();

        return redirect()->route('admin.hosting-plans.index', ['tab' => 'cloud'])
            ->with('success', 'Gameserver-Cloud-Plan wurde gelöscht.');
    }
}
