@php
    $options = $this->filterOptions();
    $quantities = $this->quantities();
    $pending = $this->pendingCount();

    $statusBadge = [
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header title="Goods Receipts" subtitle="Stock coming in against purchase orders">
        <x-slot:actions>
            @can('purchase-order-view')
                <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="shopping-cart" class="size-4" />
                    Purchase orders
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($pending > 0 && $statusFilter !== 'pending')
        <div class="alert alert-warning alert-soft">
            <x-icon name="clock" class="size-5" />
            <span>
                {{ $pending }} receipt{{ $pending === 1 ? '' : 's' }} prepared but not yet completed — no stock has
                moved for {{ $pending === 1 ? 'it' : 'them' }} yet.
            </span>
            <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('statusFilter', 'pending')">
                Show them
            </button>
        </div>
    @endif

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search receipt code, order or vendor…"
             empty-title="No goods receipts"
             empty-message="Receipts are raised from a posted purchase order.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-36" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach ($options['statuses'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-40" wire:model.live="locationFilter">
                <option value="">All locations</option>
                @foreach ($options['locations'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
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
            <x-table.heading field="code" width="140px">Receipt</x-table.heading>
            <x-table.heading width="140px">Order</x-table.heading>
            <x-table.heading>Vendor</x-table.heading>
            <x-table.heading width="160px">Location</x-table.heading>
            <x-table.heading field="gr_date" width="130px">Date</x-table.heading>
            <x-table.heading align="right" width="120px">Quantity</x-table.heading>
            <x-table.heading field="status" width="120px">Status</x-table.heading>
            <x-table.heading align="right" width="130px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $receipt)
            <tr wire:key="gr-{{ $receipt->id }}">
                <td>
                    <a href="{{ route('goods-receipts.show', $receipt) }}"
                       class="link-hover link font-mono font-medium" wire:navigate>
                        {{ $receipt->code }}
                    </a>
                </td>
                <td>
                    <a href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}"
                       class="link-hover link font-mono text-sm" wire:navigate>
                        {{ $receipt->purchaseOrder?->code }}
                    </a>
                </td>
                <td>
                    <a href="{{ route('vendors.show', $receipt->purchaseOrder->vendor_id) }}"
                       class="link-hover link" wire:navigate>
                        {{ $receipt->purchaseOrder?->vendor?->name }}
                    </a>
                </td>
                <td class="text-sm">
                    {{ $receipt->location?->name }}
                    <div class="font-mono text-xs text-base-content/50">{{ $receipt->location?->code }}</div>
                </td>
                <td class="text-sm">{{ $receipt->gr_date?->format('M d, Y') }}</td>
                <td class="tabular text-right">{{ number_format($quantities[$receipt->id] ?? 0, 2) }}</td>
                <td>
                    <span class="badge badge-sm {{ $statusBadge[$receipt->status->value] ?? 'badge-ghost' }}">
                        {{ $receipt->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('goods-receipts.show', $receipt) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                            Open
                        </a>

                        @if ($receipt->canBeEdited())
                            @can('goods-receipt-edit')
                                <a href="{{ route('goods-receipts.edit', $receipt) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                    <x-icon name="pencil-square" class="size-4" />
                                </a>
                            @endcan
                        @endif

                        @if ($receipt->canBeDeleted())
                            @can('goods-receipt-delete')
                                <button type="button"
                                        class="btn btn-ghost btn-xs text-error"
                                        wire:click="confirmDelete({{ $receipt->id }})">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete goods receipt?">
        <p class="text-sm text-base-content/70">
            @if ($receipt = $this->deletingRecord())
                This deletes pending receipt <span class="font-semibold">{{ $receipt->code }}</span> and releases the
                quantities it was holding back to the order. Completed receipts cannot be deleted; cancel them instead.
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
