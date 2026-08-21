@props([
    'title' => null,
    'breadcrumbs' => [],
])

@php
    $appName = \App\Models\Preference::get('app_name', config('app.name'));
    $appearance = $appearance ?? 'system';
    $navigation = \App\Support\Navigation::for(auth()->user());
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ $appearance }}" data-accent="{{ \App\Support\Branding::accent() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title . ' · ' . $appName : $appName }}</title>

    <link rel="icon" href="{{ \App\Support\Branding::logoUrl() }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

    {{--
        Resolve the theme before the first paint so the page never flashes the
        light theme on a dark-mode reload.
    --}}
    <script>
        (function () {
            const appearance = document.documentElement.dataset.appearance ?? 'system';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const dark = appearance === 'dark' || (appearance === 'system' && prefersDark);

            document.documentElement.dataset.theme = dark ? 'business' : 'corporate';
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-base-200 font-sans antialiased">
<div class="drawer lg:drawer-open">
    <input id="app-drawer" type="checkbox" class="drawer-toggle">

    {{-- Sidebar --}}
    <div class="drawer-side z-40">
        <label for="app-drawer" aria-label="Close menu" class="drawer-overlay"></label>

        <aside class="flex min-h-full w-64 flex-col border-r border-base-300 bg-base-100">
            <a href="{{ route('dashboard') }}"
               class="flex h-14 shrink-0 items-center gap-2 border-b border-base-300 px-4">
                <img src="{{ \App\Support\Branding::logoUrl() }}" alt="" class="size-8 rounded">
                <span class="truncate font-semibold">{{ $appName }}</span>
            </a>

            <nav class="flex-1 overflow-y-auto p-2">
                <ul class="menu w-full gap-0.5 p-0">
                    @foreach ($navigation as $item)
                        @if (isset($item['items']))
                            <li>
                                <details @if ($item['is_active']) open @endif>
                                    <summary class="font-medium">
                                        <x-icon :name="$item['icon'] ?? 'square-3-stack-3d'" class="size-4" />
                                        {{ $item['label'] }}
                                    </summary>
                                    <ul>
                                        @foreach ($item['items'] as $child)
                                            <li>
                                                <a href="{{ $child['url'] }}"
                                                   @class(['menu-active' => $child['is_active']])
                                                   @if ($child['migrated'] ?? false) wire:navigate @endif>
                                                    <x-icon :name="$child['icon'] ?? 'minus-small'" class="size-4" />
                                                    <span class="truncate">{{ $child['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            </li>
                        @else
                            <li>
                                <a href="{{ $item['url'] }}"
                                   @class(['menu-active' => $item['is_active'], 'font-medium' => true])
                                   @if ($item['migrated'] ?? false) wire:navigate @endif>
                                    <x-icon :name="$item['icon'] ?? 'square-3-stack-3d'" class="size-4" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            <div class="border-t border-base-300 p-3 text-xs text-base-content/60">
                {{ $appName }}
            </div>
        </aside>
    </div>

    {{-- Content --}}
    <div class="drawer-content flex min-h-screen flex-col">
        <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-base-300 bg-base-100 px-3">
            <label for="app-drawer" class="btn btn-square btn-ghost btn-sm lg:hidden">
                <x-icon name="bars-3" class="size-5" />
            </label>

            <x-breadcrumbs :items="$breadcrumbs" />

            {{-- Appearance is set in Settings, not from here. --}}
            <div class="ml-auto flex items-center gap-1">
                <x-user-menu />
            </div>
        </header>

        <main class="flex-1 p-4">
            {{ $slot }}
        </main>
    </div>
</div>

<x-toasts />

@livewireScripts
</body>
</html>
