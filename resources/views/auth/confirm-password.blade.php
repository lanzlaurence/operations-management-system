{{--
    Password confirmation, shown before a sensitive action. Fortify handles the
    endpoint and remembers the confirmation for the configured window.
--}}
<x-layouts.auth title="Confirm password"
                heading="Confirm your password"
                description="This is a secure area. Please confirm your password before continuing.">

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-form.field label="Password" for="password" :error="$errors->first('password')" required>
            <input id="password" name="password" type="password"
                   class="input input-bordered w-full @error('password') input-error @enderror"
                   required autofocus autocomplete="current-password">
        </x-form.field>

        <button type="submit" class="btn btn-primary w-full">Confirm password</button>
    </form>
</x-layouts.auth>
