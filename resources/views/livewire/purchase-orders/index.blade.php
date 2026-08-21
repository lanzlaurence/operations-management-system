@php
    $options = $this->filterOptions();
    $totals = $this->totals();
    $currency = \App\Models\Preference::get('currency', 'PHP');

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_received' => 'badge-warning',
        'fully_received' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header title="Purchase Orders" subtitle="What has been ordered from vendors, and what is still owed">
        <x-slot:actions>
            @can('goods-receipt-view')
                <a href="{{ route('goods-receipts.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="archive-box-arrow-down" class="size-4" />
                    Goods receipts
                </a>
            @endcan

            @can('purchase-order-create')
                <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    New Order
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search order code, reference or vendor…"
             empty-title="No purchase orders"
             empty-message="Raise one to start ordering from a vendor.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-44" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach ($options['statuses'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-40" wire:model.live="vendorFilter">
                <option value="">All vendors</option>
                @foreach ($options['vendors'] as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <input type="date" class="input input-bordered input-sm w-36" wire:model.live="fromDate" aria-label="From date">
            <input type="date" class="input input-bordered input-sm w-36" wire:model.live="toDate" aria-label="To date">

            @if ($this->hasFilters())
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">
                    <x-icon name="x-mark" class="size-4" />
                    Clear
                </button>
            @endif

            <span class="tabular ml-auto text-sm text-base-content/60">
                {{ $currency }} {{ number_format($totals['value'], 2) }}
            </span>
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="code" width="140px">Order</x-table.heading>
            <x-table.heading>Vendor</x-table.heading>
            <x-table.heading field="order_date" width="130px">Ordered</x-table.heading>
            <x-table.heading field="delivery_date" width="130px">Due</x-table.heading>
            <x-table.heading width="150px">Received</x-table.heading>
            <x-table.heading field="grand_total" align="right" width="150px">Grand total</x-table.heading>
            <x-table.heading field="status" width="150px">Status</x-table.heading>
            <x-table.heading align="right" width="140px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $order)
            @php
                $ordered = (float) $order->items()->sum('qty_ordered');
                $received = (float) $order->items()->sum('qty_received');
                $percent = $ordered > 0 ? (int) min(100, round(($received / $ordered) * 100)) : 0;
                $overdue = $order->delivery_date
                    && $order->status->isOpen()
                    && $order->delivery_date->isPast();
            @endphp

            <tr wire:key="po-{{ $order->id }}">
                <td>
                    <a href="{{ route('purchase-orders.show', $order) }}"
                       class="link-hover link font-mono font-medium"
                       wire:navigate>
                        {{ $order->code }}
                    </a>
                    @if ($order->reference_no)
                        <div class="text-xs text-base-content/50">{{ $order->reference_no }}</div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('vendors.show', $order->vendor_id) }}" class="link-hover link" wire:navigate>
                        {{ $order->vendor?->name }}
                    </a>
                    <div class="font-mono text-xs text-base-content/50">{{ $order->vendor?->code }}</div>
                </td>
                <td class="text-sm">{{ $order->order_date?->format('M d, Y') }}</td>
                <td class="text-sm">
                    @if ($order->delivery_date)
                        <span @class(['text-error font-medium' => $overdue])>
                            {{ $order->delivery_date->format('M d, Y') }}
                        </span>
                        @if ($overdue)
                            <div class="text-xs text-error">overdue</div>
                        @endif
                    @else
                        <span class="text-base-content/40">—</span>
                    @endif
                </td>
                <td>
                    @if ($order->status->isCancelled())
                        <span class="text-sm text-base-content/40">—</span>
                    @else
                        <progress class="progress progress-primary w-full" value="{{ $percent }}" max="100"></progress>
                        <div class="tabular text-xs text-base-content/50">
                            {{ number_format($received, 2) }} of {{ number_format($ordered, 2) }}
                        </div>
                    @endif
                </td>
                <td class="tabular text-right font-medium">{{ number_format((float) $order->grand_total, 2) }}</td>
                <td>
                    <span class="badge badge-sm {{ $statusBadge[$order->status->value] ?? 'badge-ghost' }}">
                        {{ $order->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                            Open
                        </a>

                        @if ($order->canCreateGr())
                            @can('goods-receipt-create')
                                <a href="{{ route('purchase-orders.goods-receipts.create', $order) }}"
                                   class="btn btn-ghost btn-xs"
                                   title="Receive against this order"
                                   wire:navigate>
                                    <x-icon name="archive-box-arrow-down" class="size-4" />
                                </a>
                            @endcan
                        @endif

                        @if ($order->canBeEdited())
                            @can('purchase-order-edit')
                                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                    <x-icon name="pencil-square" class="size-4" />
                                </a>
                            @endcan
                        @endif

                        @if ($order->canBeDeleted())
                            @can('purchase-order-delete')
                                <button type="button"
                                        class="btn btn-ghost btn-xs text-error"
                                        wire:click="confirmDelete({{ $order->id }})">
                                    <x-icon name="trash" class="size-4" />
                                </button>
                            @endcan
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete purchase order?">
        <p class="text-sm text-base-content/70">
            @if ($order = $this->deletingRecord())
                This deletes draft <span class="font-semibold">{{ $order->code }}</span>
                and any receipts prepared against it. An order that has received stock cannot be deleted;
                cancel it instead.
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
