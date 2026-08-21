{{--
    Sign-in form. Posts to Fortify's login endpoint, so the fields are named the
    way Fortify expects (`email`, `password`, `remember`).
--}}
<x-layouts.auth title="Log in"
                heading="Log in to your account"
                description="Enter your email and password below to log in">

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-form.field label="Email address" for="email" :error="$errors->first('email')" required>
            <input id="email" name="email" type="email"
                   class="input input-bordered w-full @error('email') input-error @enderror"
                   value="{{ old('email') }}"
                   required autofocus autocomplete="username">
        </x-form.field>

        <x-form.field label="Password" for="password" :error="$errors->first('password')" required>
            <input id="password" name="password" type="password"
                   class="input input-bordered w-full @error('password') input-error @enderror"
                   required autocomplete="current-password">

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="link-hover link mt-1 self-end text-xs">
                    Forgot password?
                </a>
            @endif
        </x-form.field>

        <label class="label cursor-pointer justify-start gap-2">
            <input type="checkbox" name="remember" value="1" class="checkbox checkbox-sm">
            <span class="text-sm">Remember me</span>
        </label>

        <button type="submit" class="btn btn-primary w-full">Log in</button>
    </form>
</x-layouts.auth>
