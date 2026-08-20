<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    private function isProtected(Role $role): bool
    {
        return $role->id === 1;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:role-view', only: ['index', 'show']),
            new Middleware('permission:role-create', only: ['create', 'store']),
            new Middleware('permission:role-edit', only: ['edit', 'update']),
            new Middleware('permission:role-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $roles = Role::with('permissions')->latest()->get();

        return Inertia::render('role/index', ['roles' => $roles]);
    }

    public function create(): Response
    {
        $permissions = Permission::all();

        return Inertia::render('role/create', ['permissions' => $permissions]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->name]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully');
    }

    public function show(Role $role): Response
    {
        return Inertia::render('role/show', ['role' => $role->load('permissions')]);
    }

    public function edit(Role $role): Response
    {
        $permissions = Permission::all();

        return Inertia::render('role/edit', [
            'role' => $role->load('permissions'),
            'permissions' => $permissions,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            return redirect()->route('roles.index')
                ->withErrors(['error' => 'This role cannot be modified.']);
        }

        $role->update(['name' => $request->name]);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role updated successfully');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($this->isProtected($role)) {
            return redirect()->route('roles.index')
                ->withErrors(['error' => 'This role cannot be deleted.']);
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted successfully');
    }
}
