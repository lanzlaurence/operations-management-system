@php
    $activeCode = $this->activeCode();
@endphp

<div class="space-y-4">
    <x-page-header title="Currencies" subtitle="Currencies the application can display amounts in">
        <x-slot:actions>
            @can('preference-view')
                <a href="{{ route('preferences.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="wrench-screwdriver" class="size-4" />
                    Preferences
                </a>
            @endcan

            @can('currency-create')
                <a href="{{ route('currencies.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Currency
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="alert alert-info alert-soft">
        <x-icon name="information-circle" class="size-5" />
        <span>
            Amounts are displayed with <span class="font-semibold">{{ $activeCode }}</span>, chosen in Preferences.
            Exchange rates are stored for reference; documents are recorded in the display currency.
        </span>
    </div>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search code, name or symbol…"
             empty-title="No currencies"
             empty-message="Add one before choosing it in Preferences.">

        <x-slot:head>
            <x-table.heading field="code" width="120px">Code</x-table.heading>
            <x-table.heading field="name">Name</x-table.heading>
            <x-table.heading width="110px">Symbol</x-table.heading>
            <x-table.heading field="exchange_rate" align="right" width="160px">Exchange rate</x-table.heading>
            <x-table.heading field="is_active" width="140px">Availability</x-table.heading>
            <x-table.heading align="right" width="150px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $currency)
            @php $inUse = $this->isInUse($currency); @endphp

            <tr wire:key="currency-{{ $currency->id }}">
                <td>
                    <span class="font-mono font-medium">{{ $currency->code }}</span>
                    @if ($inUse)
                        <div><span class="badge badge-primary badge-sm">In use</span></div>
                    @endif
                </td>
                <td>{{ $currency->name }}</td>
                <td class="text-lg">{{ $currency->symbol }}</td>
                <td class="tabular text-right">{{ number_format((float) $currency->exchange_rate, 6) }}</td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-neutral' => $currency->is_active,
                        'badge-ghost' => ! $currency->is_active,
                    ])>
                        {{ $currency->is_active ? 'Available' : 'Unavailable' }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('currency-edit')
                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    title="{{ $inUse && $currency->is_active
                                        ? 'The display currency cannot be made unavailable'
                                        : ($currency->is_active ? 'Make unavailable' : 'Make available') }}"
                                    wire:click="toggleActive({{ $currency->id }})"
                                    @disabled($inUse && $currency->is_active)>
                                <x-icon :name="$currency->is_active ? 'pause-circle' : 'play-circle'" class="size-4" />
                            </button>

                            <a href="{{ route('currencies.edit', $currency) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('currency-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    title="{{ $inUse ? 'The display currency cannot be deleted' : 'Delete' }}"
                                    wire:click="confirmDelete({{ $currency->id }})"
                                    @disabled($inUse)>
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete currency?">
        <p class="text-sm text-base-content/70">
            @if ($currency = $this->deletingRecord())
                This permanently deletes <span class="font-semibold">{{ $currency->code }} — {{ $currency->name }}</span>.
                Making it unavailable instead keeps it out of the Preferences list without removing the record.
            @else
                Select a record to delete.
            @endif
        </p>

        <x-slot:actions>
            <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = false">Cancel</button>
            <button type="button" class="btn btn-error btn-sm" wire:click="delete" wire:loading.attr="disabled">
                <span class="loading loading-spinner loading-xs" wire:loading wire:target="delete"></span>
                Delete
            </button>
        </x-slot:actions>
    </x-modal>
</div>
