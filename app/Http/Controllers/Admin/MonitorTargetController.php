<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMonitorTargetRequest;
use App\Http\Requests\Admin\UpdateMonitorTargetRequest;
use App\Models\MonitorTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitorTargetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MonitorTarget::class);

        $targets = MonitorTarget::query()
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/monitoring/Index', [
            'monitorTargets' => $targets,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', MonitorTarget::class);

        return Inertia::render('admin/monitoring/Create', [
            'types' => [
                ['value' => MonitorTarget::TYPE_HTTP_URL, 'label' => 'URL (HTTP/HTTPS)'],
                ['value' => MonitorTarget::TYPE_TCP_PORT, 'label' => 'TCP-Port'],
            ],
        ]);
    }

    public function store(StoreMonitorTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['config'] = $this->normalizeConfig($data['type'], $data['config']);
        $data['is_enabled'] = $request->boolean('is_enabled', true);
        MonitorTarget::query()->create($data);

        return to_route('admin.monitoring.index')->with('success', 'Monitor-Ziel angelegt.');
    }

    public function edit(Request $request, MonitorTarget $monitorTarget): Response
    {
        $this->authorize('update', $monitorTarget);

        return Inertia::render('admin/monitoring/Edit', [
            'monitorTarget' => $monitorTarget,
            'types' => [
                ['value' => MonitorTarget::TYPE_HTTP_URL, 'label' => 'URL (HTTP/HTTPS)'],
                ['value' => MonitorTarget::TYPE_TCP_PORT, 'label' => 'TCP-Port'],
            ],
        ]);
    }

    public function update(UpdateMonitorTargetRequest $request, MonitorTarget $monitorTarget): RedirectResponse
    {
        $data = $request->validated();
        $data['config'] = $this->normalizeConfig($data['type'], $data['config']);
        $data['is_enabled'] = $request->boolean('is_enabled', true);
        $monitorTarget->update($data);

        return to_route('admin.monitoring.index')->with('success', 'Monitor-Ziel aktualisiert.');
    }

    public function destroy(Request $request, MonitorTarget $monitorTarget): RedirectResponse
    {
        $this->authorize('delete', $monitorTarget);
        $monitorTarget->delete();

        return to_route('admin.monitoring.index')->with('success', 'Monitor-Ziel gelöscht.');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(string $type, array $config): array
    {
        if ($type === MonitorTarget::TYPE_HTTP_URL) {
            return [
                'url' => $config['url'] ?? '',
                'timeout' => isset($config['timeout']) ? (int) $config['timeout'] : 10,
                'expected_status' => isset($config['expected_status']) ? (int) $config['expected_status'] : 200,
            ];
        }
        if ($type === MonitorTarget::TYPE_TCP_PORT) {
            return [
                'host' => $config['host'] ?? '',
                'port' => isset($config['port']) ? (int) $config['port'] : 0,
                'timeout' => isset($config['timeout']) ? (int) $config['timeout'] : 5,
            ];
        }

        return $config;
    }
}
