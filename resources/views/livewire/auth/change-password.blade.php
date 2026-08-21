<div>
    <div class="mb-5 text-center">
        <h1 class="text-xl font-semibold">Set a new password</h1>
        <p class="mt-1 text-sm text-base-content/60">
            Your account needs a password of your own before you can continue.
        </p>
    </div>

    <form wire:submit="save" class="space-y-4">
        <x-form.field label="New password" for="password" :error="$errors->first('password')" required>
            <input id="password" type="password"
                   class="input input-bordered w-full @error('password') input-error @enderror"
                   autocomplete="new-password" autofocus
                   wire:model.blur="password">
        </x-form.field>

        <x-form.field label="Confirm password" for="password_confirmation" required>
            <input id="password_confirmation" type="password"
                   class="input input-bordered w-full"
                   autocomplete="new-password"
                   wire:model.blur="password_confirmation">
        </x-form.field>

        <x-password-requirements />

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span class="loading loading-spinner loading-xs" wire:loading wire:target="save"></span>
            Save password and continue
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm w-full">Log out</button>
    </form>
</div>
