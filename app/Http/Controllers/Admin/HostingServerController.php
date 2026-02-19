<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHostingServerRequest;
use App\Http\Requests\Admin\UpdateHostingServerRequest;
use App\Models\HostingServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HostingServerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HostingServer::class);

        $servers = HostingServer::query()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/hosting-servers/Index', [
            'hostingServers' => $servers,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', HostingServer::class);

        return Inertia::render('admin/hosting-servers/Create');
    }

    public function store(StoreHostingServerRequest $request): RedirectResponse
    {
        HostingServer::query()->create($request->validated());

        return to_route('admin.hosting-servers.index');
    }

    public function show(HostingServer $hostingServer): Response
    {
        $this->authorize('view', $hostingServer);

        $hostingServer->loadCount('webspaceAccounts');

        return Inertia::render('admin/hosting-servers/Show', [
            'hostingServer' => $hostingServer,
        ]);
    }

    public function edit(HostingServer $hostingServer): Response
    {
        $this->authorize('update', $hostingServer);

        return Inertia::render('admin/hosting-servers/Edit', [
            'hostingServer' => $hostingServer,
        ]);
    }

    public function update(UpdateHostingServerRequest $request, HostingServer $hostingServer): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['api_token'])) {
            unset($data['api_token']);
        }
        $hostingServer->update($data);

        return to_route('admin.hosting-servers.show', $hostingServer);
    }

    public function destroy(HostingServer $hostingServer): RedirectResponse
    {
        $this->authorize('delete', $hostingServer);

        $hostingServer->delete();

        return to_route('admin.hosting-servers.index');
    }
}
