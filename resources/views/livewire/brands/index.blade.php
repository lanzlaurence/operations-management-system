<div class="space-y-4">
    <x-page-header title="Brands" subtitle="Manufacturers and labels materials belong to">
        <x-slot:actions>
            @can('brand-create')
                <a href="{{ route('brands.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Brand
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search name or description…"
             empty-title="No brands"
             empty-message="Add one to group materials by manufacturer.">

        <x-slot:head>
            <x-table.heading field="name" width="240px">Name</x-table.heading>
            <x-table.heading>Description</x-table.heading>
            <x-table.heading field="created_at" width="150px">Created</x-table.heading>
            <x-table.heading field="updated_at" width="150px">Updated</x-table.heading>
            <x-table.heading align="right" width="120px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $brand)
            <tr wire:key="brand-{{ $brand->id }}">
                <td class="font-medium">{{ $brand->name }}</td>
                <td class="text-base-content/70">{{ $brand->description ?: '—' }}</td>
                <td class="text-sm text-base-content/60">{{ $brand->created_at?->format('M d, Y') }}</td>
                <td class="text-sm text-base-content/60">{{ $brand->updated_at?->format('M d, Y') }}</td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('brand-edit')
                            <a href="{{ route('brands.edit', $brand) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('brand-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $brand->id }})">
                                <x-icon name="trash" class="size-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete brand?">
        <p class="text-sm text-base-content/70">
            @if ($brand = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $brand->name }}</span>. Materials that referenced it
                keep their history, but the brand can no longer be selected.
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
