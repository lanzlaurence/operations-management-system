@aware([
    'sort' => null,
    'direction' => 'asc',
])

@props([
    'field' => null,
    'align' => 'left',
    'width' => null,
])

{{--
    A table header cell. Pass `field` to make it sortable - clicking it calls
    `sortBy()` on the Livewire component, and the arrow reflects the current
    order (both read from the parent <x-table> through @aware).
--}}
@php
    $isSorted = $field !== null && $sort === $field;

    $alignment = match ($align) {
        'right' => 'text-right',
        'center' => 'text-center',
        default => 'text-left',
    };
@endphp

<th @class(['whitespace-nowrap', $alignment])
    @style(["width: {$width}" => $width])>
    @if ($field)
        <button type="button"
                wire:click="sortBy('{{ $field }}')"
                @class([
                    'inline-flex items-center gap-1 hover:text-base-content',
                    'justify-end' => $align === 'right',
                    'justify-center' => $align === 'center',
                    'font-semibold text-base-content' => $isSorted,
                ])>
            {{ $slot }}

            @if ($isSorted)
                <x-icon :name="$direction === 'asc' ? 'bars-arrow-up' : 'bars-arrow-down'" class="size-3.5" />
            @else
                <x-icon name="arrows-up-down" class="size-3.5 opacity-30" />
            @endif
        </button>
    @else
        {{ $slot }}
    @endif
</th>
