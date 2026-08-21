{{-- Requests a reset link through Fortify. --}}
<x-layouts.auth title="Forgot password"
                heading="Forgot your password?"
                description="Enter your email and we will send a reset link">

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form.field label="Email address" for="email" :error="$errors->first('email')" required>
            <input id="email" name="email" type="email"
                   class="input input-bordered w-full @error('email') input-error @enderror"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="username">
        </x-form.field>

        <button type="submit" class="btn btn-primary w-full">Email password reset link</button>

        <p class="text-center text-sm text-base-content/60">
            Or, return to <a href="{{ route('login') }}" class="link">log in</a>
        </p>
    </form>
</x-layouts.auth>
