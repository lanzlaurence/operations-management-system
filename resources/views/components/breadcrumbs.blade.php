@props([
    'items' => [],
])

{{--
    Breadcrumbs for the page header.

    Pages pass `:breadcrumbs="[['label' => 'UOM', 'route' => 'uoms.index'], ['label' => 'Create']]"`;
    the last entry renders as plain text because it is the current page.
--}}
@php
    $items = collect($items)->values();
@endphp

<div class="breadcrumbs min-w-0 flex-1 text-sm">
    <ul>
        <li>
            <a href="{{ route('dashboard') }}" class="text-base-content/60 hover:text-base-content">
                <x-icon name="home" class="size-4" />
            </a>
        </li>

        @foreach ($items as $index => $item)
            @php
                $label = is_array($item) ? ($item['label'] ?? '') : $item;
                $url = is_array($item)
                    ? ($item['url'] ?? (isset($item['route']) && \Illuminate\Support\Facades\Route::has($item['route'])
                        ? route($item['route'], $item['parameters'] ?? [])
                        : null))
                    : null;
                $isLast = $index === $items->count() - 1;
            @endphp

            <li>
                @if ($url && ! $isLast)
                    <a href="{{ $url }}" class="text-base-content/60 hover:text-base-content">
                        {{ $label }}
                    </a>
                @else
                    <span @class(['font-medium' => $isLast, 'truncate' => true])>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
