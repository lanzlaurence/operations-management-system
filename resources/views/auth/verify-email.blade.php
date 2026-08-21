{{--
    Email verification notice. Both actions post to Fortify: resend the link, or
    sign out.
--}}
<x-layouts.auth title="Verify email"
                heading="Verify your email address"
                description="We sent a verification link to your inbox. Open it to finish signing in.">

    <div class="space-y-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary w-full">Resend verification email</button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm w-full">Log out</button>
        </form>
    </div>
</x-layouts.auth>
