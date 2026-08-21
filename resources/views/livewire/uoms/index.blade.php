<div class="space-y-4">
    <x-page-header title="Unit of Measurement" subtitle="Units materials are counted, weighed and sold in">
        <x-slot:actions>
            @can('uom-create')
                <a href="{{ route('uoms.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add UOM
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search acronym or description…"
             empty-title="No units of measurement"
             empty-message="Add one to start using it on materials.">

        <x-slot:head>
            <x-table.heading field="acronym" width="140px">Acronym</x-table.heading>
            <x-table.heading>Description</x-table.heading>
            <x-table.heading field="created_at" width="150px">Created</x-table.heading>
            <x-table.heading field="updated_at" width="150px">Updated</x-table.heading>
            <x-table.heading align="right" width="120px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $uom)
            <tr wire:key="uom-{{ $uom->id }}">
                <td class="font-medium">{{ $uom->acronym }}</td>
                <td class="text-base-content/70">{{ $uom->description ?: '—' }}</td>
                <td class="text-sm text-base-content/60">{{ $uom->created_at?->format('M d, Y') }}</td>
                <td class="text-sm text-base-content/60">{{ $uom->updated_at?->format('M d, Y') }}</td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('uom-edit')
                            <a href="{{ route('uoms.edit', $uom) }}"
                               class="btn btn-ghost btn-xs"
                               wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('uom-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $uom->id }})">
                                <x-icon name="trash" class="size-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete unit of measurement?">
        <p class="text-sm text-base-content/70">
            @if ($uom = $this->deletingRecord())
                This permanently deletes <span class="font-semibold">{{ $uom->acronym }}</span>.
                Materials already using it keep their reference, but it can no longer be selected.
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
