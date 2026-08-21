@props([
    'paginator' => null,
    'sort' => null,
    'direction' => 'asc',
    'searchable' => true,
    'searchPlaceholder' => 'Search…',
    'perPage' => true,
    'emptyTitle' => 'Nothing to show',
    'emptyMessage' => 'No records match the current filters.',
])

{{--
    The one table used by every index screen.

    Search, sorting and paging are driven by the WithDataTable trait on the
    Livewire component that renders this, so a screen only has to supply its
    columns and rows:

        <x-table :paginator="$uoms" :sort="$sortField" :direction="$sortDirection">
            <x-slot:head>
                <x-table.heading field="acronym">Acronym</x-table.heading>
                <x-table.heading>Actions</x-table.heading>
            </x-slot:head>

            @foreach ($uoms as $uom) <tr>…</tr> @endforeach
        </x-table>

    `head` is a slot; the rows are the default slot. `toolbar` adds controls
    next to the search box (filters, an export button, …).
--}}
<div {{ $attributes->merge(['class' => 'rounded-box border border-base-300 bg-base-100']) }}>
    @if ($searchable || isset($toolbar))
        <div class="flex flex-wrap items-center gap-2 border-b border-base-300 p-3">
            @if ($searchable)
                <label class="input input-sm input-bordered w-full max-w-xs gap-2">
                    <x-icon name="magnifying-glass" class="size-4 opacity-60" />
                    <input type="search"
                           class="grow"
                           placeholder="{{ $searchPlaceholder }}"
                           wire:model.live.debounce.300ms="search">
                </label>

                <span class="loading loading-spinner loading-xs" wire:loading wire:target="search"></span>
            @endif

            {{ $toolbar ?? '' }}

            @if ($paginator)
                <span class="ml-auto text-sm text-base-content/60">
                    {{ number_format($paginator->total()) }} {{ str('record')->plural($paginator->total()) }}
                </span>
            @endif
        </div>
    @endif

    <div class="overflow-x-auto">
        <table class="table-sticky table table-zebra table-sm">
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
            <tbody>
                {{ $slot }}

                @if ($paginator && $paginator->isEmpty())
                    <tr>
                        <td colspan="100" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <x-icon name="inbox" class="size-8 opacity-30" />
                                <p class="font-medium">{{ $emptyTitle }}</p>
                                <p class="text-sm text-base-content/60">{{ $emptyMessage }}</p>
                            </div>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if ($paginator)
        <div class="flex flex-wrap items-center gap-3 border-t border-base-300 p-3">
            @if ($perPage)
                <label class="flex items-center gap-2 text-sm text-base-content/60">
                    Rows
                    <select class="select select-bordered select-sm w-20" wire:model.live="perPage">
                        @foreach ($perPageOptions ?? [10, 25, 50, 100] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="ml-auto">
                {{ $paginator->links() }}
            </div>
        </div>
    @endif
</div>
