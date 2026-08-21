<div class="space-y-4">
    <x-page-header title="Categories" subtitle="How materials are grouped for reporting">
        <x-slot:actions>
            @can('category-create')
                <a href="{{ route('categories.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Category
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search name or description…"
             empty-title="No categories"
             empty-message="Add one to start grouping materials.">

        <x-slot:head>
            <x-table.heading field="name" width="240px">Name</x-table.heading>
            <x-table.heading>Description</x-table.heading>
            <x-table.heading field="created_at" width="150px">Created</x-table.heading>
            <x-table.heading field="updated_at" width="150px">Updated</x-table.heading>
            <x-table.heading align="right" width="120px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $category)
            <tr wire:key="category-{{ $category->id }}">
                <td class="font-medium">{{ $category->name }}</td>
                <td class="text-base-content/70">{{ $category->description ?: '—' }}</td>
                <td class="text-sm text-base-content/60">{{ $category->created_at?->format('M d, Y') }}</td>
                <td class="text-sm text-base-content/60">{{ $category->updated_at?->format('M d, Y') }}</td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('category-edit')
                            <a href="{{ route('categories.edit', $category) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('category-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $category->id }})">
                                <x-icon name="trash" class="size-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete category?">
        <p class="text-sm text-base-content/70">
            @if ($category = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $category->name }}</span>. Materials that referenced it
                keep their history, but the category can no longer be selected.
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
