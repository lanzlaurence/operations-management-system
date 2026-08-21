@php
    $catalogue = $this->catalogue();
    $granted = $this->grantedCount();
    $total = $this->permissionTotal();
@endphp

<div class="mx-auto max-w-4xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $role->name : 'Create Role'"
                   subtitle="Tick what this role may do; users inherit it from the role">
        <x-slot:actions>
            <a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        <x-card title="Role">
            <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required
                          hint="Name it after the job, e.g. Warehouse Staff or Purchasing Officer.">
                <input id="name" type="text"
                       class="input input-bordered w-full max-w-md @error('form.name') input-error @enderror"
                       maxlength="255" autofocus
                       wire:model.blur="form.name">
            </x-form.field>
        </x-card>

        {{-- Permission matrix, grouped by module then subject --}}
        <x-card title="Permissions">
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <span class="tabular text-sm text-base-content/60">
                        {{ number_format($granted) }} of {{ number_format($total) }} selected
                    </span>
                    <button type="button" class="btn btn-ghost btn-xs" wire:click="selectAll">All</button>
                    <button type="button" class="btn btn-ghost btn-xs" wire:click="selectNone">None</button>
                </div>
            </x-slot:header>

            @error('form.permissions')
                <p class="mb-3 text-sm text-error">{{ $message }}</p>
            @enderror

            <div class="space-y-4">
                @foreach ($catalogue as $group => $subjects)
                    @php
                        $groupPermissions = collect($subjects)->flatMap(fn ($abilities) => array_values($abilities))->all();
                        $groupAll = $this->allGranted($groupPermissions);
                        $groupSome = $this->someGranted($groupPermissions);
                    @endphp

                    <div class="rounded-box border border-base-300">
                        <div class="flex items-center justify-between gap-2 border-b border-base-300 bg-base-200 px-3 py-2">
                            <label class="flex cursor-pointer items-center gap-2">
                                <input type="checkbox"
                                       class="checkbox checkbox-sm"
                                       @checked($groupAll)
                                       @if ($groupSome && ! $groupAll) indeterminate="true" @endif
                                       wire:click="toggleGroup('{{ $group }}')">
                                <span class="font-semibold">{{ $group }}</span>
                            </label>

                            <span class="tabular text-xs text-base-content/60">
                                {{ count(array_intersect($groupPermissions, $form->permissions)) }} / {{ count($groupPermissions) }}
                            </span>
                        </div>

                        <div class="divide-y divide-base-300">
                            @foreach ($subjects as $subject => $abilities)
                                @php $subjectPermissions = array_values($abilities); @endphp

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-3 py-2">
                                    <label class="flex w-44 cursor-pointer items-center gap-2">
                                        <input type="checkbox"
                                               class="checkbox checkbox-xs"
                                               @checked($this->allGranted($subjectPermissions))
                                               wire:click="toggleSubject(@js($subjectPermissions))">
                                        <span class="text-sm font-medium">
                                            {{ \App\Support\PermissionCatalog::subjectLabel($subject) }}
                                        </span>
                                    </label>

                                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                                        @foreach ($abilities as $ability => $permission)
                                            <label class="flex cursor-pointer items-center gap-1.5 text-sm">
                                                <input type="checkbox"
                                                       class="checkbox checkbox-xs"
                                                       value="{{ $permission }}"
                                                       wire:model.live="form.permissions">
                                                {{ \App\Support\PermissionCatalog::abilityLabel($ability) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($granted === 0)
                <div class="alert alert-warning alert-soft mt-4">
                    <x-icon name="exclamation-triangle" class="size-5" />
                    <span>A role with no permissions lets its users sign in but reach nothing.</span>
                </div>
            @endif
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create Role' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
