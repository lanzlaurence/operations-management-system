{{--
    Sets a new password from an emailed reset link. The token and email come
    from the link and travel as hidden fields, which is what Fortify expects.
--}}
<x-layouts.auth title="Reset password"
                heading="Reset your password"
                description="Choose a new password for your account">

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.field label="Email address" for="email" :error="$errors->first('email')" required>
            <input id="email" name="email" type="email"
                   class="input input-bordered w-full @error('email') input-error @enderror"
                   value="{{ old('email', $email) }}"
                   required readonly autocomplete="username">
        </x-form.field>

        <x-form.field label="New password" for="password" :error="$errors->first('password')" required>
            <input id="password" name="password" type="password"
                   class="input input-bordered w-full @error('password') input-error @enderror"
                   required autofocus autocomplete="new-password">
        </x-form.field>

        <x-form.field label="Confirm password" for="password_confirmation" required>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="input input-bordered w-full"
                   required autocomplete="new-password">
        </x-form.field>

        <x-password-requirements />

        <button type="submit" class="btn btn-primary w-full">Reset password</button>
    </form>
</x-layouts.auth>
