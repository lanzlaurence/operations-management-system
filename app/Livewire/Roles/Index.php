<?php

namespace App\Livewire\Roles;

use App\Livewire\Support\MasterIndex;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles and what each one can do.
 *
 * The first role is the seeded administrator and is left alone: it holds every
 * permission, and editing it is how an installation locks itself out. A role
 * still assigned to users cannot be deleted either - those accounts would
 * silently lose their access.
 */
class Index extends MasterIndex
{
    /** The seeded administrator role. */
    private const PROTECTED_ID = 1;

    protected function model(): string
    {
        return Role::class;
    }

    protected function permissionPrefix(): string
    {
        return 'role';
    }

    protected function label(): string
    {
        return 'Role';
    }

    protected function view(): string
    {
        return 'livewire.roles.index';
    }

    /**
     * @return array<int, string>
     */
    protected function withRelations(): array
    {
        return ['permissions:id,name'];
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name'];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'created_at'];
    }

    protected function defaultSortField(): string
    {
        return 'name';
    }

    protected function defaultSortDirection(): string
    {
        return 'asc';
    }

    public function isProtected(Role $role): bool
    {
        return $role->id === self::PROTECTED_ID;
    }

    /**
     * How many accounts hold each role, so removing one is an informed choice.
     *
     * @return array<int, int>
     */
    public function userCounts(): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->all();
    }

    /**
     * Total permissions available, for the "12 of 84" reading on each row.
     */
    public function permissionTotal(): int
    {
        return Permission::query()->count();
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        if ($this->isProtected($record)) {
            return 'The administrator role cannot be deleted.';
        }

        $users = $this->userCounts()[$record->id] ?? 0;

        return $users === 0
            ? null
            : "{$record->name} is assigned to {$users} user(s). Reassign them first.";
    }
}
