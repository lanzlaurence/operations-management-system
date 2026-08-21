<?php

namespace App\Livewire\Roles;

use App\Livewire\Forms\RoleForm;
use App\Livewire\Support\MasterForm;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Livewire\Form as LivewireForm;
use Spatie\Permission\Models\Role;

/**
 * Create and edit screen for a role.
 *
 * The permission matrix is grouped by module and subject, with row and group
 * toggles, because picking from a flat list of eighty checkboxes is how roles
 * end up with the wrong access.
 */
class Form extends MasterForm
{
    /** The seeded administrator role. */
    private const PROTECTED_ID = 1;

    public RoleForm $form;

    public ?Role $role = null;

    public function mount(?Role $role = null): void
    {
        if (! $role?->exists) {
            return;
        }

        if ($role->id === self::PROTECTED_ID) {
            abort(403, 'The administrator role cannot be edited.');
        }

        $this->role = $role;
        $this->form->setRole($role);
    }

    protected function formObject(): LivewireForm
    {
        return $this->form;
    }

    protected function record(): ?Model
    {
        return $this->role;
    }

    protected function indexRoute(): string
    {
        return 'roles.index';
    }

    protected function label(): string
    {
        return 'Role';
    }

    protected function view(): string
    {
        return 'livewire.roles.form';
    }

    /**
     * The permission matrix: group => subject => ability => permission name.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function catalogue(): array
    {
        return PermissionCatalog::grouped();
    }

    /**
     * Grant or revoke every permission in one subject row.
     *
     * @param  array<int, string>  $permissions
     */
    public function toggleSubject(array $permissions): void
    {
        $this->form->permissions = $this->allGranted($permissions)
            ? array_values(array_diff($this->form->permissions, $permissions))
            : array_values(array_unique([...$this->form->permissions, ...$permissions]));
    }

    /**
     * Grant or revoke a whole module group.
     */
    public function toggleGroup(string $group): void
    {
        $permissions = collect($this->catalogue()[$group] ?? [])
            ->flatMap(fn (array $abilities): array => array_values($abilities))
            ->all();

        $this->toggleSubject($permissions);
    }

    public function selectAll(): void
    {
        $this->form->permissions = PermissionCatalog::all();
    }

    public function selectNone(): void
    {
        $this->form->permissions = [];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function allGranted(array $permissions): bool
    {
        return $permissions !== [] && array_diff($permissions, $this->form->permissions) === [];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function someGranted(array $permissions): bool
    {
        return array_intersect($permissions, $this->form->permissions) !== [];
    }

    public function grantedCount(): int
    {
        return count($this->form->permissions);
    }

    public function permissionTotal(): int
    {
        return count(PermissionCatalog::all());
    }
}
