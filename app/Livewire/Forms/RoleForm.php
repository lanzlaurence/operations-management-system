<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Role;

/**
 * The role form, shared by the create and edit screens.
 *
 * A role is a name plus a set of permissions, so the interesting part is the
 * permission matrix the screen builds around this: permissions are stored flat
 * (`purchase-order-post`) and grouped for display by their subject.
 */
class RoleForm extends Form
{
    public ?Role $role = null;

    public string $name = '';

    /**
     * Permission names granted to the role.
     *
     * @var array<int, string>
     */
    public array $permissions = [];

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('name')->all();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($this->role?->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a role name, for example Warehouse Staff.',
            'name.unique' => 'A role with that name already exists.',
        ];
    }

    protected function prepareForValidation($attributes)
    {
        $attributes['name'] = trim((string) ($attributes['name'] ?? ''));
        $attributes['permissions'] = array_values(array_unique($attributes['permissions'] ?? []));

        return $attributes;
    }

    public function save(): Role
    {
        $data = $this->validate();

        $role = $this->role ?? new Role(['guard_name' => config('auth.defaults.guard')]);

        $role->name = $data['name'];
        $role->save();

        $role->syncPermissions($data['permissions'] ?? []);

        $this->role = $role;

        return $role;
    }
}
