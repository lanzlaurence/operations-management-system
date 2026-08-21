{{--
    Pagination for Livewire tables, styled with DaisyUI join buttons.

    Used through `WithDataTable::paginationView()`. `$paginator` and `$elements`
    are provided by Livewire's paginator.
--}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3" role="navigation" aria-label="Pagination">
        <p class="hidden text-sm text-base-content/60 sm:block">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>

        <div class="join ml-auto">
            @if ($paginator->onFirstPage())
                <button class="btn join-item btn-sm btn-disabled" aria-disabled="true">
                    <x-icon name="chevron-left" class="size-4" />
                </button>
            @else
                <button type="button" class="btn join-item btn-sm"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        rel="prev" aria-label="Previous page">
                    <x-icon name="chevron-left" class="size-4" />
                </button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <button class="btn join-item btn-sm btn-disabled">{{ $element }}</button>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <button type="button"
                                @class(['btn', 'join-item', 'btn-sm', 'btn-active' => $page === $paginator->currentPage()])
                                wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                wire:loading.attr="disabled">
                            {{ $page }}
                        </button>
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" class="btn join-item btn-sm"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        rel="next" aria-label="Next page">
                    <x-icon name="chevron-right" class="size-4" />
                </button>
            @else
                <button class="btn join-item btn-sm btn-disabled" aria-disabled="true">
                    <x-icon name="chevron-right" class="size-4" />
                </button>
            @endif
        </div>
    </nav>
@endif
