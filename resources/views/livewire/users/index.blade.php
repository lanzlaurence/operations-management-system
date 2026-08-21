@php
    $options = $this->filterOptions();
@endphp

<div class="space-y-4">
    <x-page-header title="Users" subtitle="Who can sign in, and what they are allowed to do">
        <x-slot:actions>
            @can('user-create')
                <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add User
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search name or email…"
             empty-title="No users"
             empty-message="Nothing matches the current filters.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-40" wire:model.live="roleFilter">
                <option value="">All roles</option>
                @foreach ($options['roles'] as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-48" wire:model.live="stateFilter">
                <option value="">Any state</option>
                @foreach ($options['states'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            @if ($this->hasFilters())
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">
                    <x-icon name="x-mark" class="size-4" />
                    Clear
                </button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="name">Name</x-table.heading>
            <x-table.heading field="email" width="240px">Email</x-table.heading>
            <x-table.heading width="200px">Roles</x-table.heading>
            <x-table.heading width="200px">State</x-table.heading>
            <x-table.heading field="password_changed_at" width="150px">Password set</x-table.heading>
            <x-table.heading align="right" width="170px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $user)
            @php
                $protected = $this->isProtected($user);
                $reason = $this->protectedReason($user);
            @endphp

            <tr wire:key="user-{{ $user->id }}">
                <td>
                    <div class="flex items-center gap-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral text-xs text-neutral-content">
                            {{ str($user->name)->substr(0, 1)->upper() }}
                        </span>
                        <div>
                            <div class="font-medium">{{ $user->name }}</div>
                            @if ($user->id === auth()->id())
                                <div class="text-xs text-base-content/50">This is you</div>
                            @elseif ($protected)
                                <div class="text-xs text-base-content/50">System administrator</div>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="text-sm">
                    {{ $user->email }}
                    @unless ($user->email_verified_at)
                        <div class="text-xs text-warning">Unverified</div>
                    @endunless
                </td>
                <td>
                    @forelse ($user->roles as $role)
                        <span class="badge badge-ghost badge-sm">{{ $role->name }}</span>
                    @empty
                        <span class="text-sm text-base-content/40">No role</span>
                    @endforelse
                </td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        @if ($user->is_locked)
                            <span class="badge badge-error badge-sm">Locked</span>
                        @endif

                        <span @class(['badge badge-sm', 'badge-neutral' => $user->is_active, 'badge-ghost' => ! $user->is_active])>
                            {{ $user->is_active ? 'Active' : 'Deactivated' }}
                        </span>

                        @if ($user->force_password_change)
                            <span class="badge badge-warning badge-sm">Must change password</span>
                        @endif
                    </div>
                </td>
                <td class="text-sm text-base-content/60">
                    {{ $user->password_changed_at?->format('M d, Y') ?? 'Never' }}
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('user-edit')
                            @if ($user->is_locked)
                                <button type="button" class="btn btn-ghost btn-xs" title="Unlock account"
                                        wire:click="unlock({{ $user->id }})">
                                    <x-icon name="lock-open" class="size-4" />
                                </button>
                            @endif

                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    title="{{ $reason ?? ($user->is_active ? 'Deactivate' : 'Activate') }}"
                                    wire:click="toggleActive({{ $user->id }})"
                                    @disabled($protected)>
                                <x-icon :name="$user->is_active ? 'pause-circle' : 'play-circle'" class="size-4" />
                            </button>

                            @if ($protected)
                                <span class="btn btn-ghost btn-xs btn-disabled" title="{{ $reason }}">
                                    <x-icon name="pencil-square" class="size-4" />
                                </span>
                            @else
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                    <x-icon name="pencil-square" class="size-4" />
                                    Edit
                                </a>
                            @endif
                        @endcan

                        @can('user-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    title="{{ $reason ?? 'Delete' }}"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    @disabled($protected)>
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete user?">
        <p class="text-sm text-base-content/70">
            @if ($user = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $user->name }}</span> ({{ $user->email }}).
                Documents they created keep their name in the audit trail.
                Deactivating instead keeps the account and blocks sign-in.
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
