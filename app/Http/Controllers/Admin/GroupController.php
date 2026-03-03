<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGroupRequest;
use App\Http\Requests\Admin\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        $groups = Group::query()
            ->with('permissions:id,key,name,label')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'label', 'color']);

        return Inertia::render('admin/groups/Index', [
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        $permissions = Permission::query()->orderBy('category')->orderBy('key')->get(['id', 'key', 'name', 'label', 'category']);
        $groupsForCopy = Group::query()
            ->with('permissions:id')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'label'])
            ->map(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'label' => $g->label,
                'permission_ids' => $g->permissions->pluck('id')->values()->all(),
            ]);

        return Inertia::render('admin/groups/Create', [
            'permissions' => $permissions,
            'groupsForCopy' => $groupsForCopy,
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $group = Group::create($validated);
        $group->permissions()->sync($permissionIds);

        return redirect()->route('admin.groups.index')->with('success', 'Gruppe angelegt.');
    }

    public function edit(Group $group): Response
    {
        $group->load('permissions:id');
        $permissions = Permission::query()->orderBy('category')->orderBy('key')->get(['id', 'key', 'name', 'label', 'category']);
        $groupsForCopy = Group::query()
            ->where('id', '!=', $group->id)
            ->with('permissions:id')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'label'])
            ->map(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'label' => $g->label,
                'permission_ids' => $g->permissions->pluck('id')->values()->all(),
            ]);

        return Inertia::render('admin/groups/Edit', [
            'group' => $group,
            'permissions' => $permissions,
            'groupsForCopy' => $groupsForCopy,
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $group->update($validated);
        $group->permissions()->sync($permissionIds);

        return redirect()->route('admin.groups.index')->with('success', 'Gruppe aktualisiert.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        return redirect()->route('admin.groups.index')->with('success', 'Gruppe gelöscht.');
    }
}
