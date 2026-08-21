<div class="mx-auto max-w-3xl space-y-4">
    <x-page-header :title="$this->isEditing() ? 'Edit ' . $user->name : 'Create User'"
                   :subtitle="$this->isEditing() ? $user->email : 'The account can sign in as soon as it is created'">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        {{-- Identity --}}
        <x-card title="Account">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Name" for="name" :error="$errors->first('form.name')" required>
                    <input id="name" type="text"
                           class="input input-bordered w-full @error('form.name') input-error @enderror"
                           maxlength="255" autofocus
                           wire:model.blur="form.name">
                </x-form.field>

                <x-form.field label="Email" for="email" :error="$errors->first('form.email')" required
                              hint="This is the sign-in name.">
                    <input id="email" type="email"
                           class="input input-bordered w-full @error('form.email') input-error @enderror"
                           maxlength="255"
                           wire:model.blur="form.email">
                </x-form.field>
            </div>
        </x-card>

        {{-- Password --}}
        <x-card title="Password"
                :subtitle="$this->isEditing() ? 'Leave blank to keep the current password' : 'Set the initial password'">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.field label="Password" for="password" :error="$errors->first('form.password')"
                              :required="! $this->isEditing()">
                    <input id="password" type="password"
                           class="input input-bordered w-full @error('form.password') input-error @enderror"
                           autocomplete="new-password"
                           wire:model.blur="form.password">
                </x-form.field>

                <x-form.field label="Confirm password" for="password_confirmation"
                              :required="! $this->isEditing()">
                    <input id="password_confirmation" type="password"
                           class="input input-bordered w-full"
                           autocomplete="new-password"
                           wire:model.blur="form.password_confirmation">
                </x-form.field>

                <div class="sm:col-span-2">
                    <ul class="grid gap-1 text-xs text-base-content/60 sm:grid-cols-2">
                        @foreach ($this->passwordRules() as $rule)
                            <li class="flex items-center gap-1">
                                <x-icon name="check" class="size-3.5 opacity-50" />
                                {{ $rule }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-card>

        {{-- Roles --}}
        <x-card title="Roles" subtitle="A user's permissions come from the roles assigned here">
            @php $roles = $this->roles(); @endphp

            @if ($roles->isEmpty())
                <p class="text-sm text-base-content/60">
                    No roles exist yet.
                    @can('role-create')
                        <a href="{{ route('roles.create') }}" class="link" wire:navigate>Create one first</a>.
                    @endcan
                </p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $role)
                        <label @class([
                            'flex cursor-pointer items-start gap-3 rounded-box border p-3 transition',
                            'border-primary bg-primary/5' => in_array($role->name, $form->roles, true),
                            'border-base-300 hover:border-base-content/30' => ! in_array($role->name, $form->roles, true),
                        ])>
                            <input type="checkbox" class="checkbox checkbox-sm mt-0.5"
                                   value="{{ $role->name }}"
                                   wire:model.live="form.roles">
                            <span>
                                <span class="font-medium">{{ $role->name }}</span>
                                <span class="block text-xs text-base-content/60">
                                    {{ $role->permissions_count }} permission{{ $role->permissions_count === 1 ? '' : 's' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('form.roles')
                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                @enderror

                @if ($form->roles === [])
                    <div class="alert alert-warning alert-soft mt-3">
                        <x-icon name="exclamation-triangle" class="size-5" />
                        <span>Without a role this account can sign in but reach nothing.</span>
                    </div>
                @endif
            @endif
        </x-card>

        {{-- Access flags --}}
        <x-card title="Access">
            <div class="space-y-3">
                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" class="toggle toggle-sm" wire:model="form.is_active">
                    <span>
                        Active
                        <span class="block text-xs text-base-content/60">
                            Deactivating blocks sign-in without deleting the account.
                        </span>
                    </span>
                </label>

                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" class="toggle toggle-sm" wire:model="form.email_verified">
                    <span>
                        Email verified
                        <span class="block text-xs text-base-content/60">
                            Unverified accounts are asked to confirm their address before continuing.
                        </span>
                    </span>
                </label>

                <label class="label cursor-pointer justify-start gap-3">
                    <input type="checkbox" class="toggle toggle-sm" wire:model="form.force_password_change">
                    <span>
                        Must change password at next sign-in
                        <span class="block text-xs text-base-content/60">
                            Recommended whenever you set a password on someone's behalf.
                        </span>
                    </span>
                </label>

                @if ($this->isEditing())
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" class="toggle toggle-sm" wire:model="form.is_locked">
                        <span>
                            Locked out
                            <span class="block text-xs text-base-content/60">
                                Set by repeated failed sign-ins. Clearing it also resets the attempt counter
                                @if ($user->login_attempts)
                                    (currently {{ $user->login_attempts }}).
                                @else
                                    .
                                @endif
                            </span>
                        </span>
                    </label>
                @endif
            </div>
        </x-card>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    {{ $this->isEditing() ? 'Save changes' : 'Create User' }}
                </button>

                @unless ($this->isEditing())
                    <button type="button" class="btn btn-ghost btn-sm"
                            wire:click="saveAndAddAnother" wire:loading.attr="disabled">
                        Save and add another
                    </button>
                @endunless

                <a href="{{ route('users.index') }}" class="btn btn-ghost btn-sm ml-auto" wire:navigate>Cancel</a>
            </div>
        </x-card>
    </form>
</div>
