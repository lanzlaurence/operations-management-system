@php
    $totals = $this->totals();
    $locations = $this->stockByLocation();
    $currency = \App\Models\Preference::get('currency', 'PHP');
    $uom = $material->uom?->acronym;

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_received' => 'badge-warning',
        'fully_received' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header title="Purchase history" :subtitle="$material->code . ' — ' . $material->name">
        <x-slot:actions>
            <a href="{{ route('materials.sales-history', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-up-tray" class="size-4" />
                Sales history
            </a>

            <a href="{{ route('materials.show', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back to material
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Totals for the lines currently matched --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Quantity ordered</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ number_format($totals['qty_ordered'], 2) }}
                <span class="text-sm font-normal text-base-content/50">{{ $uom }}</span>
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Quantity received</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($totals['qty_received'], 2) }}</p>
            <p class="text-xs text-base-content/50">
                {{ number_format(max(0, $totals['qty_ordered'] - $totals['qty_received']), 2) }} still outstanding
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Net cost</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($totals['net_cost'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Excluding VAT and charges</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Average unit cost</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($totals['avg_unit_cost'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">
                List {{ number_format((float) $material->unit_cost, 2) }}
            </p>
        </div>
    </div>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search order code, reference or vendor…"
             empty-title="No purchases"
             empty-message="This material has never been ordered.">

        <x-slot:head>
            <x-table.heading width="130px">Order</x-table.heading>
            <x-table.heading>Vendor</x-table.heading>
            <x-table.heading field="order_date" width="130px">Order date</x-table.heading>
            <x-table.heading align="right" width="120px">Qty ordered</x-table.heading>
            <x-table.heading align="right" width="120px">Received</x-table.heading>
            <x-table.heading field="unit_cost_after_discount" align="right" width="140px">Unit cost</x-table.heading>
            <x-table.heading field="net_price" align="right" width="140px">Net cost</x-table.heading>
        </x-slot:head>

        @foreach ($records as $line)
            <tr wire:key="po-item-{{ $line->id }}">
                <td>
                    <a href="{{ route('purchase-orders.show', $line->purchase_order_id) }}" class="link-hover link font-mono">
                        {{ $line->purchaseOrder?->code }}
                    </a>

                    @if ($line->purchaseOrder)
                        <div>
                            <span class="badge badge-xs {{ $statusBadge[$line->purchaseOrder->status->value] ?? 'badge-ghost' }}">
                                {{ $line->purchaseOrder->status->label() }}
                            </span>
                        </div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('vendors.show', $line->purchaseOrder->vendor_id) }}" class="link-hover link" wire:navigate>
                        {{ $line->purchaseOrder?->vendor?->name }}
                    </a>
                    <div class="font-mono text-xs text-base-content/50">{{ $line->purchaseOrder?->vendor?->code }}</div>
                </td>
                <td>{{ $line->purchaseOrder?->order_date?->format('M d, Y') }}</td>
                <td class="tabular text-right">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                <td class="tabular text-right">
                    {{ number_format((float) $line->qty_received, 2) }}
                    @if ((float) $line->qty_received < (float) $line->qty_ordered)
                        <div class="text-xs text-base-content/50">
                            {{ number_format((float) $line->qty_ordered - (float) $line->qty_received, 2) }} open
                        </div>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format((float) $line->unit_cost_after_discount, 2) }}
                    @if ((float) $line->discount_amount > 0)
                        <div class="text-xs text-base-content/50">
                            from {{ number_format((float) $line->unit_cost, 2) }}
                        </div>
                    @endif
                </td>
                <td class="tabular text-right font-medium">{{ number_format((float) $line->net_price, 2) }}</td>
            </tr>
        @endforeach
    </x-table>

    <x-card title="Stock by location" subtitle="Where this material sits right now">
        @if ($locations->isEmpty())
            <p class="text-sm text-base-content/60">This material is not stocked anywhere yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th class="w-28">Location</th>
                            <th>Name</th>
                            <th class="text-right">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locations as $row)
                            <tr>
                                <td class="font-mono">{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td class="tabular text-right">{{ number_format($row->quantity, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
