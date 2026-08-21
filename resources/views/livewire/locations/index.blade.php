@php
    $stockCounts = $this->stockCounts();
@endphp

<div class="space-y-4">
    <x-page-header title="Locations" subtitle="Warehouses, stores, hubs and distribution centres">
        <x-slot:actions>
            @can('location-create')
                <a href="{{ route('locations.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Location
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search code, name or description…"
             empty-title="No locations"
             empty-message="Add one before receiving or issuing stock.">

        <x-slot:head>
            <x-table.heading field="code" width="140px">Code</x-table.heading>
            <x-table.heading field="name" width="240px">Name</x-table.heading>
            <x-table.heading>Description</x-table.heading>
            <x-table.heading align="right" width="120px">Stock items</x-table.heading>
            <x-table.heading field="created_at" width="150px">Created</x-table.heading>
            <x-table.heading align="right" width="120px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $location)
            <tr wire:key="location-{{ $location->id }}">
                <td>
                    <span class="badge badge-ghost badge-sm font-mono">{{ $location->code }}</span>
                </td>
                <td class="font-medium">{{ $location->name }}</td>
                <td class="text-base-content/70">{{ $location->description ?: '—' }}</td>
                <td class="tabular text-right">
                    {{ number_format($stockCounts[$location->id] ?? 0) }}
                </td>
                <td class="text-sm text-base-content/60">{{ $location->created_at?->format('M d, Y') }}</td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('location-edit')
                            <a href="{{ route('locations.edit', $location) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('location-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $location->id }})">
                                <x-icon name="trash" class="size-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete location?">
        <p class="text-sm text-base-content/70">
            @if ($location = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $location->code }} — {{ $location->name }}</span>.
                Locations holding stock or referenced by a receipt or issue cannot be deleted.
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
