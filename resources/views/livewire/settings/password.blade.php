<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header title="Settings" subtitle="Your personal settings for this application" />

    <x-settings-nav />

    <form wire:submit="save">
        <x-card title="Change password"
                subtitle="Use a password you do not use anywhere else">
            <div class="space-y-4">
                <x-form.field label="Current password" for="current_password"
                              :error="$errors->first('current_password')" required>
                    <input id="current_password" type="password"
                           class="input input-bordered w-full @error('current_password') input-error @enderror"
                           autocomplete="current-password"
                           wire:model.blur="current_password">
                </x-form.field>

                <x-form.field label="New password" for="password" :error="$errors->first('password')" required>
                    <input id="password" type="password"
                           class="input input-bordered w-full @error('password') input-error @enderror"
                           autocomplete="new-password"
                           wire:model.blur="password">
                </x-form.field>

                <x-form.field label="Confirm new password" for="password_confirmation" required>
                    <input id="password_confirmation" type="password"
                           class="input input-bordered w-full"
                           autocomplete="new-password"
                           wire:model.blur="password_confirmation">
                </x-form.field>

                <x-password-requirements />
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    Change password
                </button>

                @if ($this->lastChangedAt())
                    <p class="ml-auto text-xs text-base-content/50">
                        Last changed {{ $this->lastChangedAt() }}
                    </p>
                @endif
            </x-slot:footer>
        </x-card>
    </form>
</div>
