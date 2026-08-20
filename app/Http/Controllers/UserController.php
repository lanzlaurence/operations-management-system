<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    private function isProtected(User $user): bool
    {
        return $user->id === 1;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:user-view', only: ['index', 'show']),
            new Middleware('permission:user-create', only: ['create', 'store']),
            new Middleware('permission:user-edit', only: ['edit', 'update']),
            new Middleware('permission:user-delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $users = User::with('roles')->latest()->get();

        return Inertia::render('user/index', ['users' => $users]);
    }

    public function create(): Response
    {
        $roles = Role::all();

        return Inertia::render('user/create', ['roles' => $roles]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'email_verified_at' => $request->email_verified ? now() : null,
            'password' => Hash::make($request->password),
            'force_password_change' => $request->force_password_change ?? true,
            'is_active' => $request->is_active ?? true,
        ]);

        if ($request->roles) {
            $user->syncRoles($request->roles);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function show(User $user): Response
    {
        return Inertia::render('user/show', ['user' => $user->load('roles')]);
    }

    public function edit(User $user): Response
    {
        $roles = Role::all();

        return Inertia::render('user/edit', [
            'user' => $user->load('roles'),
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return redirect()->route('users.index')
                ->withErrors(['error' => 'This user cannot be modified.']);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'email_verified_at' => $request->email_verified ? ($user->email_verified_at ?? now()) : null,
            'force_password_change' => $request->force_password_change ?? false,
            'is_active' => $request->is_active ?? true,
            'is_locked' => $request->is_locked ?? false,
            'login_attempts' => ! $request->is_locked ? 0 : $user->login_attempts,
        ]);

        if ($request->password) {
            $user->update([
                'password' => Hash::make($request->password),
                'password_changed_at' => now(),
            ]);
        }

        if ($request->roles) {
            $user->syncRoles($request->roles);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return redirect()->route('users.index')
                ->withErrors(['error' => 'This user cannot be deleted.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    }
}
