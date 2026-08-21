@php
    $user = auth()->user();
    $initials = collect(str($user?->name ?? '')->explode(' '))
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => str($part)->substr(0, 1)->upper()->value())
        ->implode('');
@endphp

{{-- Account menu: profile, password, and sign out. --}}
<div class="dropdown dropdown-end">
    <button type="button" class="btn btn-ghost btn-sm gap-2 px-2" aria-label="Account">
        <span class="avatar avatar-placeholder">
            <span class="flex size-7 items-center justify-center rounded-full bg-neutral text-xs text-neutral-content">
                {{ $initials ?: '?' }}
            </span>
        </span>
        <span class="hidden max-w-32 truncate text-sm font-medium sm:inline">{{ $user?->name }}</span>
        <x-icon name="chevron-down" class="size-4 opacity-60" />
    </button>

    <div class="dropdown-content z-50 w-56 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
        <div class="px-3 py-2">
            <p class="truncate text-sm font-medium">{{ $user?->name }}</p>
            <p class="truncate text-xs text-base-content/60">{{ $user?->email }}</p>
            @if ($user?->roles->isNotEmpty())
                <p class="mt-1 text-xs text-base-content/60">{{ $user->roles->pluck('name')->implode(', ') }}</p>
            @endif
        </div>

        <div class="divider my-0"></div>

        <ul class="menu w-full p-0">
            @if (\Illuminate\Support\Facades\Route::has('profile.edit'))
                <li>
                    <a href="{{ route('profile.edit') }}">
                        <x-icon name="user" class="size-4" />
                        Profile
                    </a>
                </li>
            @endif

            @if (\Illuminate\Support\Facades\Route::has('user-password.edit'))
                <li>
                    <a href="{{ route('user-password.edit') }}">
                        <x-icon name="key" class="size-4" />
                        Password
                    </a>
                </li>
            @endif

            @if (\Illuminate\Support\Facades\Route::has('appearance.edit'))
                <li>
                    <a href="{{ route('appearance.edit') }}">
                        <x-icon name="paint-brush" class="size-4" />
                        Appearance
                    </a>
                </li>
            @endif

            <li>
                <button type="submit" form="logout-form" class="text-error">
                    <x-icon name="arrow-left-start-on-rectangle" class="size-4" />
                    Log out
                </button>
            </li>
        </ul>

        {{-- Kept outside the menu so the button above inherits the menu styling. --}}
        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
            @csrf
        </form>
    </div>
</div>
