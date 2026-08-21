@php
    $options = $this->filterOptions();
    $totals = $this->totals();
    $lastMoved = $this->lastMovedAt();
    $currency = \App\Models\Preference::get('currency', 'PHP');
@endphp

<div class="space-y-4">
    <x-page-header title="Inventory" subtitle="Stock balances per material and location">
        <x-slot:actions>
            @can('inventory-adjust')
                <a href="{{ route('inventories.manual-adjustment') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="adjustments-horizontal" class="size-4" />
                    Manual adjustment
                </a>
            @endcan

            @can('activity-inventory-log')
                <a href="{{ route('activity.inventory-log') }}" class="btn btn-ghost btn-sm">
                    <x-icon name="clipboard-document-list" class="size-4" />
                    Movement log
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Totals across everything the filters match, not just this page --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Stock records</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($totals['records']) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Total quantity</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($totals['quantity'], 2) }}</p>
            <p class="text-xs text-base-content/50">Across mixed units of measurement</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Stock value</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($totals['value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">At average purchase cost</p>
        </div>
    </div>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search stock code, material or location…"
             empty-title="No stock records"
             empty-message="Records appear once stock is received or opened manually.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-40" wire:model.live="locationFilter">
                <option value="">All locations</option>
                @foreach ($options['locations'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-40" wire:model.live="stockFilter">
                <option value="">Any balance</option>
                @foreach ($options['stock'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            @if ($this->hasFilters())
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">
                    <x-icon name="x-mark" class="size-4" />
                    Clear
                </button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="code" width="120px">Code</x-table.heading>
            <x-table.heading>Material</x-table.heading>
            <x-table.heading width="180px">Location</x-table.heading>
            <x-table.heading field="quantity" align="right" width="140px">On hand</x-table.heading>
            <x-table.heading align="right" width="140px">Value</x-table.heading>
            <x-table.heading field="updated_at" width="150px">Last movement</x-table.heading>
            <x-table.heading align="right" width="110px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $inventory)
            @php
                $quantity = (float) $inventory->quantity;
                $reorder = (int) ($inventory->material->reorder_level ?? 0);
                $atReorder = $reorder > 0 && $quantity <= $reorder;
            @endphp

            <tr wire:key="inventory-{{ $inventory->id }}">
                <td>
                    <a href="{{ route('inventories.show', $inventory) }}"
                       class="link-hover link font-mono font-medium"
                       wire:navigate>
                        {{ $inventory->code }}
                    </a>
                </td>
                <td>
                    <a href="{{ route('materials.show', $inventory->material_id) }}"
                       class="link-hover link font-medium"
                       wire:navigate>
                        {{ $inventory->material?->name }}
                    </a>
                    <div class="font-mono text-xs text-base-content/50">{{ $inventory->material?->code }}</div>
                </td>
                <td>
                    {{ $inventory->location?->name }}
                    <div class="font-mono text-xs text-base-content/50">{{ $inventory->location?->code }}</div>
                </td>
                <td class="tabular text-right">
                    <span @class(['font-medium', 'text-error' => $atReorder, 'text-base-content/40' => $quantity <= 0])>
                        {{ number_format($quantity, 2) }}
                    </span>
                    <span class="text-xs text-base-content/50">{{ $inventory->material?->uom?->acronym }}</span>

                    @if ($atReorder && $quantity > 0)
                        <div class="text-xs text-error">at reorder ({{ $reorder }})</div>
                    @elseif ($quantity <= 0)
                        <div class="text-xs text-base-content/40">empty</div>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format($quantity * (float) ($inventory->material->avg_unit_cost ?? 0), 2) }}
                </td>
                <td class="text-sm text-base-content/60">
                    {{ isset($lastMoved[$inventory->id])
                        ? \Illuminate\Support\Carbon::parse($lastMoved[$inventory->id])->format('M d, Y')
                        : '—' }}
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('inventories.show', $inventory) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                            View
                        </a>

                        @can('inventory-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $inventory->id }})">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete stock record?">
        <p class="text-sm text-base-content/70">
            @if ($inventory = $this->deletingRecord())
                This removes <span class="font-semibold">{{ $inventory->code }}</span>
                ({{ $inventory->material?->name }} at {{ $inventory->location?->name }}).
                Its movement history stays in the inventory log, and receiving the material here again recreates it.
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
