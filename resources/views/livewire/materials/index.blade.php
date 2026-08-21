@php
    $options = $this->filterOptions();
    $stock = $this->stockOnHand();
    $currency = \App\Models\Preference::get('currency', 'PHP');
@endphp

<div class="space-y-4">
    <x-page-header title="Materials" subtitle="Everything the business buys, stocks and sells">
        <x-slot:actions>
            @can('material-create')
                <a href="{{ route('materials.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Material
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search code, SKU, name, brand or category…"
             empty-title="No materials"
             empty-message="Add one before raising an order or receiving stock.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-40" wire:model.live="categoryFilter">
                <option value="">All categories</option>
                @foreach ($options['categories'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-36" wire:model.live="brandFilter">
                <option value="">All brands</option>
                @foreach ($options['brands'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-32" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach ($options['statuses'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <label class="label cursor-pointer gap-2 text-sm">
                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="needsReorder">
                At reorder level
            </label>

            @if ($this->hasFilters())
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">
                    <x-icon name="x-mark" class="size-4" />
                    Clear
                </button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="code" width="110px">Code</x-table.heading>
            <x-table.heading field="name">Material</x-table.heading>
            <x-table.heading field="sku" width="120px">SKU</x-table.heading>
            <x-table.heading width="150px">Classification</x-table.heading>
            <x-table.heading align="right" width="120px">On hand</x-table.heading>
            <x-table.heading field="unit_cost" align="right" width="130px">Cost</x-table.heading>
            <x-table.heading field="unit_price" align="right" width="130px">Price</x-table.heading>
            <x-table.heading field="status" width="100px">Status</x-table.heading>
            <x-table.heading align="right" width="200px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $material)
            @php
                $onHand = $stock[$material->id] ?? 0;
                $atReorder = $material->reorder_level > 0 && $onHand <= $material->reorder_level;
            @endphp

            <tr wire:key="material-{{ $material->id }}">
                <td>
                    <a href="{{ route('materials.show', $material) }}"
                       class="link-hover link font-mono font-medium"
                       wire:navigate>
                        {{ $material->code }}
                    </a>
                </td>
                <td>
                    <div class="font-medium">{{ $material->name }}</div>
                    @if ($material->description)
                        <div class="line-clamp-1 text-xs text-base-content/60">{{ $material->description }}</div>
                    @endif
                </td>
                <td class="font-mono text-sm text-base-content/70">{{ $material->sku ?: '—' }}</td>
                <td class="text-sm text-base-content/70">
                    {{ $material->category?->name ?: '—' }}
                    @if ($material->brand)
                        <div class="text-xs text-base-content/50">{{ $material->brand->name }}</div>
                    @endif
                </td>
                <td class="tabular text-right">
                    <span @class(['font-medium', 'text-error' => $atReorder])>
                        {{ number_format($onHand, 2) }}
                    </span>
                    <span class="text-xs text-base-content/50">{{ $material->uom?->acronym }}</span>

                    @if ($atReorder)
                        <div class="text-xs text-error">at reorder ({{ (int) $material->reorder_level }})</div>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format((float) $material->unit_cost, 2) }}
                    <div class="text-xs text-base-content/50">avg {{ number_format((float) $material->avg_unit_cost, 2) }}</div>
                </td>
                <td class="tabular text-right">
                    {{ number_format((float) $material->unit_price, 2) }}
                    <div class="text-xs text-base-content/50">avg {{ number_format((float) $material->avg_unit_price, 2) }}</div>
                </td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-neutral' => $material->status === \App\Enums\RecordStatus::Active,
                        'badge-ghost' => $material->status !== \App\Enums\RecordStatus::Active,
                    ])>
                        {{ $material->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('materials.purchase-history', $material) }}"
                           class="btn btn-ghost btn-xs"
                           title="Purchase history"
                           wire:navigate>
                            <x-icon name="arrow-down-tray" class="size-4" />
                        </a>

                        <a href="{{ route('materials.sales-history', $material) }}"
                           class="btn btn-ghost btn-xs"
                           title="Sales history"
                           wire:navigate>
                            <x-icon name="arrow-up-tray" class="size-4" />
                        </a>

                        <a href="{{ route('materials.show', $material) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                        </a>

                        @can('material-edit')
                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    wire:click="toggleStatus({{ $material->id }})"
                                    title="{{ $material->status === \App\Enums\RecordStatus::Active ? 'Set inactive' : 'Set active' }}">
                                <x-icon :name="$material->status === \App\Enums\RecordStatus::Active ? 'pause-circle' : 'play-circle'"
                                        class="size-4" />
                            </button>

                            <a href="{{ route('materials.edit', $material) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                            </a>
                        @endcan

                        @can('material-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $material->id }})">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete material?">
        <p class="text-sm text-base-content/70">
            @if ($material = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $material->code }} — {{ $material->name }}</span>.
                Materials holding stock or appearing on orders cannot be deleted; set them inactive instead.
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
