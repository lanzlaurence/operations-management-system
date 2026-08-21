@php
    $userCounts = $this->userCounts();
    $permissionTotal = $this->permissionTotal();
@endphp

<div class="space-y-4">
    <x-page-header title="Roles" subtitle="Permission sets assigned to user accounts">
        <x-slot:actions>
            @can('role-create')
                <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Role
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search role name…"
             empty-title="No roles"
             empty-message="Create one to start granting access.">

        <x-slot:head>
            <x-table.heading field="name" width="220px">Role</x-table.heading>
            <x-table.heading align="right" width="140px">Permissions</x-table.heading>
            <x-table.heading align="right" width="110px">Users</x-table.heading>
            <x-table.heading>Covers</x-table.heading>
            <x-table.heading align="right" width="140px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $role)
            @php
                $protected = $this->isProtected($role);
                $granted = $role->permissions->count();
                $users = $userCounts[$role->id] ?? 0;

                // Which modules this role touches, read off its permission names.
                $subjects = $role->permissions
                    ->map(fn ($permission) => \App\Support\PermissionCatalog::subjectLabel(
                        str($permission->name)->beforeLast('-')->value()
                    ))
                    ->unique()
                    ->sort()
                    ->take(6);
            @endphp

            <tr wire:key="role-{{ $role->id }}">
                <td>
                    <div class="font-medium">{{ $role->name }}</div>
                    @if ($protected)
                        <div class="text-xs text-base-content/50">System role</div>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format($granted) }}
                    <span class="text-xs text-base-content/50">of {{ number_format($permissionTotal) }}</span>

                    @if ($permissionTotal > 0)
                        <progress class="progress progress-primary mt-1 w-full"
                                  value="{{ $granted }}" max="{{ $permissionTotal }}"></progress>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format($users) }}
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @forelse ($subjects as $subject)
                            <span class="badge badge-ghost badge-sm">{{ $subject }}</span>
                        @empty
                            <span class="text-sm text-base-content/40">No permissions</span>
                        @endforelse

                        @if ($role->permissions->count() > 0 && $subjects->count() === 6)
                            <span class="text-xs text-base-content/50">and more</span>
                        @endif
                    </div>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('role-edit')
                            @if ($protected)
                                <span class="btn btn-ghost btn-xs btn-disabled"
                                      title="The administrator role cannot be edited">
                                    <x-icon name="pencil-square" class="size-4" />
                                </span>
                            @else
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                    <x-icon name="pencil-square" class="size-4" />
                                    Edit
                                </a>
                            @endif
                        @endcan

                        @can('role-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    title="{{ $protected ? 'The administrator role cannot be deleted' : 'Delete' }}"
                                    wire:click="confirmDelete({{ $role->id }})"
                                    @disabled($protected)>
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete role?">
        <p class="text-sm text-base-content/70">
            @if ($role = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $role->name }}</span> and the
                {{ $role->permissions->count() }} permission(s) it grants. A role still assigned to users
                cannot be deleted.
            @else
                Select a record to delete.
            @endif
        </p>

        <x-slot:actions>
            <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = false">Cancel</button>
            <button type="button" class="btn btn-error btn-sm" wire:click="delete" wire:loading.attr="disabled">
                <span class="loading loading-spinner loading-xs" wire:loading wire:target="delete"></span>
                Delete
            </button>
        </x-slot:actions>
    </x-modal>
</div>
