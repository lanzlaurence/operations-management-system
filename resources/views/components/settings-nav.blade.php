{{--
    Tab strip shared by the personal settings screens. Entries whose route does
    not exist are skipped, which keeps this working while routes move around.
--}}
@php
    $tabs = collect([
        ['label' => 'Profile', 'route' => 'profile.edit', 'icon' => 'user'],
        ['label' => 'Password', 'route' => 'user-password.edit', 'icon' => 'key'],
        ['label' => 'Appearance', 'route' => 'appearance.edit', 'icon' => 'paint-brush'],
    ])->filter(fn (array $tab): bool => \Illuminate\Support\Facades\Route::has($tab['route']));
@endphp

<div role="tablist" class="tabs tabs-box w-fit">
    @foreach ($tabs as $tab)
        <a role="tab"
           href="{{ route($tab['route']) }}"
           @class(['tab gap-2', 'tab-active' => request()->routeIs($tab['route'])])
           wire:navigate>
            <x-icon :name="$tab['icon']" class="size-4" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
