@props([
    'title',
    'subtitle' => null,
])

{{-- Page title with an optional description and a slot for actions. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-3']) }}>
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-semibold">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-base-content/60">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
