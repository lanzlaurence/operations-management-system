@php
    $totals = $this->totals();
    $locations = $this->stockByLocation();
    $currency = \App\Models\Preference::get('currency', 'PHP');
    $uom = $material->uom?->acronym;

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_shipped' => 'badge-warning',
        'fully_shipped' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header title="Sales history" :subtitle="$material->code . ' — ' . $material->name">
        <x-slot:actions>
            <a href="{{ route('materials.purchase-history', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-down-tray" class="size-4" />
                Purchase history
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
            <p class="text-sm text-base-content/60">Quantity shipped</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($totals['qty_shipped'], 2) }}</p>
            <p class="text-xs text-base-content/50">
                {{ number_format(max(0, $totals['qty_ordered'] - $totals['qty_shipped']), 2) }} still to ship
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Net revenue</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($totals['net_revenue'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Excluding VAT and charges</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Average unit price</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($totals['avg_unit_price'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">
                Against average cost {{ number_format((float) $material->avg_unit_cost, 2) }}
            </p>
        </div>
    </div>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search order code, reference or customer…"
             empty-title="No sales"
             empty-message="This material has never been sold.">

        <x-slot:head>
            <x-table.heading width="130px">Order</x-table.heading>
            <x-table.heading>Customer</x-table.heading>
            <x-table.heading field="order_date" width="130px">Order date</x-table.heading>
            <x-table.heading align="right" width="120px">Qty ordered</x-table.heading>
            <x-table.heading align="right" width="120px">Shipped</x-table.heading>
            <x-table.heading field="unit_price_after_discount" align="right" width="140px">Unit price</x-table.heading>
            <x-table.heading field="net_price" align="right" width="140px">Net revenue</x-table.heading>
        </x-slot:head>

        @foreach ($records as $line)
            <tr wire:key="so-item-{{ $line->id }}">
                <td>
                    <a href="{{ route('sales-orders.show', $line->sales_order_id) }}" class="link-hover link font-mono">
                        {{ $line->salesOrder?->code }}
                    </a>

                    @if ($line->salesOrder)
                        <div>
                            <span class="badge badge-xs {{ $statusBadge[$line->salesOrder->status->value] ?? 'badge-ghost' }}">
                                {{ $line->salesOrder->status->label() }}
                            </span>
                        </div>
                    @endif
                </td>
                <td>
                    <a href="{{ route('customers.show', $line->salesOrder->customer_id) }}" class="link-hover link" wire:navigate>
                        {{ $line->salesOrder?->customer?->name }}
                    </a>
                    <div class="font-mono text-xs text-base-content/50">{{ $line->salesOrder?->customer?->code }}</div>
                </td>
                <td>{{ $line->salesOrder?->order_date?->format('M d, Y') }}</td>
                <td class="tabular text-right">{{ number_format((float) $line->qty_ordered, 2) }}</td>
                <td class="tabular text-right">
                    {{ number_format((float) $line->qty_shipped, 2) }}
                    @if ((float) $line->qty_shipped < (float) $line->qty_ordered)
                        <div class="text-xs text-base-content/50">
                            {{ number_format((float) $line->qty_ordered - (float) $line->qty_shipped, 2) }} open
                        </div>
                    @endif
                </td>
                <td class="tabular text-right">
                    {{ number_format((float) $line->unit_price_after_discount, 2) }}
                    @if ((float) $line->discount_amount > 0)
                        <div class="text-xs text-base-content/50">
                            from {{ number_format((float) $line->unit_price, 2) }}
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
