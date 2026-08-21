@props([
    'title' => null,
    'subtitle' => null,
])

{{-- Panel used for forms and detail sections. --}}
<div {{ $attributes->merge(['class' => 'rounded-box border border-base-300 bg-base-100']) }}>
    @if ($title || isset($header))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 px-4 py-3">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="truncate font-semibold">{{ $title }}</h2>
                @endif

                @if ($subtitle)
                    <p class="text-sm text-base-content/60">{{ $subtitle }}</p>
                @endif
            </div>

            {{ $header ?? '' }}
        </div>
    @endif

    <div {{ ($slotAttributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'p-4']) }}>
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="flex flex-wrap items-center gap-2 border-t border-base-300 px-4 py-3">
            {{ $footer }}
        </div>
    @endif
</div>
