<div class="mx-auto max-w-2xl space-y-4">
    <x-page-header title="Settings" subtitle="Your personal settings for this application" />

    <x-settings-nav />

    {{-- Name and email --}}
    <form wire:submit="save">
        <x-card title="Profile" subtitle="Your name and the email address you sign in with">
            <div class="space-y-4">
                <x-form.field label="Name" for="name" :error="$errors->first('name')" required>
                    <input id="name" type="text"
                           class="input input-bordered w-full @error('name') input-error @enderror"
                           maxlength="255" autocomplete="name"
                           wire:model.blur="name">
                </x-form.field>

                <x-form.field label="Email address" for="email" :error="$errors->first('email')" required
                              hint="Changing this means verifying the new address before you can continue.">
                    <input id="email" type="email"
                           class="input input-bordered w-full @error('email') input-error @enderror"
                           maxlength="255" autocomplete="username"
                           wire:model.blur="email">
                </x-form.field>

                @if ($this->mustVerifyEmail() && ! $this->isVerified())
                    <div class="alert alert-warning alert-soft">
                        <x-icon name="exclamation-triangle" class="size-5" />
                        <span>
                            This address is not verified yet.
                            <button type="button" class="link font-medium" wire:click="resendVerification">
                                Resend the verification link
                            </button>.
                        </span>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
                    Save changes
                </button>
            </x-slot:footer>
        </x-card>
    </form>

    {{-- Account removal, behind a password confirmation --}}
    <x-card title="Delete account"
            subtitle="This removes your account and signs you out. It cannot be undone.">
        <button type="button" class="btn btn-error btn-outline btn-sm"
                x-on:click="$dispatch('open-modal', { name: 'delete-account' })">
            <x-icon name="trash" class="size-4" />
            Delete my account
        </button>

        <x-modal name="delete-account" title="Delete your account?">
            <p class="text-sm text-base-content/70">
                Everything you created stays on record for audit purposes, but the account itself is removed
                and you will be signed out immediately. Confirm with your password to continue.
            </p>

            <div class="mt-3">
                <x-form.field label="Password" for="deletePassword" :error="$errors->first('deletePassword')" required>
                    <input id="deletePassword" type="password"
                           class="input input-bordered w-full @error('deletePassword') input-error @enderror"
                           autocomplete="current-password"
                           wire:model.blur="deletePassword">
                </x-form.field>
            </div>

            <x-slot:actions>
                <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = false">Cancel</button>
                <button type="button" class="btn btn-error btn-sm"
                        wire:click="deleteAccount" wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="deleteAccount"></span>
                    Delete account
                </button>
            </x-slot:actions>
        </x-modal>
    </x-card>
</div>
