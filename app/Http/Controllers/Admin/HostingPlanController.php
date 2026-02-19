<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHostingPlanRequest;
use App\Http\Requests\Admin\UpdateHostingPlanRequest;
use App\Models\HostingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HostingPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HostingPlan::class);

        $plans = HostingPlan::query()
            ->withCount('webspaceAccounts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/hosting-plans/Index', [
            'hostingPlans' => $plans,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', HostingPlan::class);

        return Inertia::render('admin/hosting-plans/Create');
    }

    public function store(StoreHostingPlanRequest $request): RedirectResponse
    {
        $plan = HostingPlan::query()->create($request->validated());

        $plan->product()->create([
            'name' => $plan->name,
            'key' => Str::slug($plan->name).'_'.Str::random(4),
            'type' => 'webspace',
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
        ]);

        return to_route('admin.hosting-plans.index');
    }

    public function show(HostingPlan $hostingPlan): Response
    {
        $this->authorize('view', $hostingPlan);

        $hostingPlan->load(['product', 'webspaceAccounts' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('admin/hosting-plans/Show', [
            'hostingPlan' => $hostingPlan,
        ]);
    }

    public function edit(HostingPlan $hostingPlan): Response
    {
        $this->authorize('update', $hostingPlan);

        return Inertia::render('admin/hosting-plans/Edit', [
            'hostingPlan' => $hostingPlan,
        ]);
    }

    public function update(UpdateHostingPlanRequest $request, HostingPlan $hostingPlan): RedirectResponse
    {
        $hostingPlan->update($request->validated());

        $hostingPlan->product?->update([
            'name' => $hostingPlan->name,
            'is_active' => $hostingPlan->is_active,
            'sort_order' => $hostingPlan->sort_order,
        ]);

        return to_route('admin.hosting-plans.show', $hostingPlan);
    }

    public function destroy(HostingPlan $hostingPlan): RedirectResponse
    {
        $this->authorize('delete', $hostingPlan);

        $hostingPlan->product?->delete();
        $hostingPlan->delete();

        return to_route('admin.hosting-plans.index');
    }
}
