@props([
    'title' => null,
    'heading' => null,
    'description' => null,
])

@php
    $appName = \App\Support\Branding::appName();
    $appearance = $appearance ?? 'system';
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

    {{-- Same pre-paint theme resolution as the application layout. --}}
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
<div class="flex min-h-screen flex-col items-center justify-center gap-6 p-4">
    <a href="{{ route('home') }}" class="flex items-center gap-3">
        <img src="{{ \App\Support\Branding::logoUrl() }}" alt="" class="size-10 rounded">
        <span class="text-lg font-semibold">{{ $appName }}</span>
    </a>

    <div class="w-full max-w-md rounded-box border border-base-300 bg-base-100 p-6 shadow-sm">
        @if ($heading)
            <div class="mb-5 text-center">
                <h1 class="text-xl font-semibold">{{ $heading }}</h1>

                @if ($description)
                    <p class="mt-1 text-sm text-base-content/60">{{ $description }}</p>
                @endif
            </div>
        @endif

        {{-- Fortify puts its confirmations here (reset link sent, verification sent, ...) --}}
        @if (session('status'))
            <div class="alert alert-success alert-soft mb-4">
                <x-icon name="check-circle" class="size-5" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{ $slot }}
    </div>

    <p class="text-xs text-base-content/40">{{ $appName }}</p>
</div>

<x-toasts />

@livewireScripts
</body>
</html>
