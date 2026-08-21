@php
    $appName = \App\Support\Branding::appName();
    $appearance = $appearance ?? 'system';
@endphp

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ $appearance }}" data-accent="{{ \App\Support\Branding::accent() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $appName }} — purchasing, sales and inventory in one place.">
    <title>{{ $appName }}</title>

    <link rel="icon" href="{{ \App\Support\Branding::logoUrl() }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">

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
</head>

<body class="min-h-screen bg-base-200 font-sans antialiased">
<div class="flex min-h-screen flex-col">
    <header class="border-b border-base-300 bg-base-100">
        <div class="mx-auto flex h-16 w-full max-w-5xl items-center gap-3 px-4">
            <img src="{{ \App\Support\Branding::logoUrl() }}" alt="" class="size-9 rounded">
            <span class="font-semibold">{{ $appName }}</span>

            <div class="ml-auto">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">
                        Go to dashboard
                        <x-icon name="arrow-right" class="size-4" />
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Log in</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-16">
        <div>
            <h1 class="text-3xl font-semibold sm:text-4xl">
                Purchasing, sales and inventory in one place
            </h1>

            <p class="mt-3 text-base-content/70">
                {{ $appName }} keeps orders, stock movements and costing in step, so what the system says is
                on the shelf is what is actually there.
            </p>

            <ul class="mt-6 space-y-3">
                @foreach ([
                    ['shopping-cart', 'Purchase orders and receiving', 'Order from vendors, receive in stages, and let stock and average cost follow automatically.'],
                    ['shopping-bag', 'Sales orders and shipping', 'Confirm customer orders, ship in stages, and see what is still owed.'],
                    ['building-storefront', 'Inventory that reconciles', 'Every movement is logged, so a balance can always be explained.'],
                    ['chart-bar', 'A trail you can audit', 'Status changes and field-level edits are recorded with who did them and when.'],
                ] as [$icon, $title, $description])
                    <li class="flex gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                            <x-icon :name="$icon" class="size-5" />
                        </span>
                        <span>
                            <span class="font-medium">{{ $title }}</span>
                            <span class="block text-sm text-base-content/60">{{ $description }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Open the dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Log in</a>

                    @if (\Illuminate\Support\Facades\Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="btn btn-ghost">Forgot your password?</a>
                    @endif
                @endauth
            </div>
        </div>

    </main>

    <footer class="border-t border-base-300 bg-base-100">
        <div class="mx-auto w-full max-w-5xl px-4 py-4 text-xs text-base-content/50">
            {{ $appName }}
        </div>
    </footer>
</div>
</body>
</html>
