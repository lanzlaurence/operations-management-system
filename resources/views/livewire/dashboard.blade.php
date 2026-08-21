@php
    $stats = $this->stats();
    $trend = $this->trend();
    $byCategory = $this->stockByCategory();
    $topStock = $this->topStockValue();
    $topSold = $this->topSoldValue();
    $lowStock = $this->lowStock();
    $orderStatus = $this->orderStatus();
    $currency = \App\Models\Preference::get('currency', 'PHP');

    $money = fn (float $value): string => $currency . ' ' . number_format($value, 2);
@endphp

<div class="space-y-4">
    <x-page-header title="Dashboard" subtitle="Stock position, trading activity and what needs attention">
        <x-slot:actions>
            <div class="join">
                @foreach (\App\Livewire\Dashboard::WINDOWS as $window)
                    <button type="button"
                            @class(['btn join-item btn-sm', 'btn-active' => $months === $window])
                            wire:click="$set('months', {{ $window }})">
                        {{ $window }}m
                    </button>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- Headline figures --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <div class="flex items-start justify-between">
                <p class="text-sm text-base-content/60">Stock value</p>
                <x-icon name="building-storefront" class="size-5 opacity-30" />
            </div>
            <p class="tabular mt-1 text-2xl font-semibold">{{ $money($stats['stock_value']) }}</p>
            <p class="text-xs text-base-content/50">
                {{ number_format($stats['stock_qty'], 2) }} units over {{ number_format($stats['materials']) }} materials
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <div class="flex items-start justify-between">
                <p class="text-sm text-base-content/60">Sales value</p>
                <x-icon name="shopping-bag" class="size-5 opacity-30" />
            </div>
            <p class="tabular mt-1 text-2xl font-semibold">{{ $money($stats['sales_value']) }}</p>
            <p class="text-xs text-base-content/50">{{ number_format($stats['open_so']) }} orders still to ship</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <div class="flex items-start justify-between">
                <p class="text-sm text-base-content/60">Purchase value</p>
                <x-icon name="shopping-cart" class="size-5 opacity-30" />
            </div>
            <p class="tabular mt-1 text-2xl font-semibold">{{ $money($stats['purchase_value']) }}</p>
            <p class="text-xs text-base-content/50">{{ number_format($stats['open_po']) }} orders awaiting delivery</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <div class="flex items-start justify-between">
                <p class="text-sm text-base-content/60">Needs attention</p>
                <x-icon name="exclamation-triangle" class="size-5 opacity-30" />
            </div>
            <p @class(['tabular mt-1 text-2xl font-semibold', 'text-error' => $stats['low_stock'] > 0])>
                {{ number_format($stats['low_stock']) }}
            </p>
            <p class="text-xs text-base-content/50">
                at reorder level, {{ number_format($stats['out_of_stock']) }} out of stock
            </p>
        </div>
    </div>

    {{-- Realised margin: what shipped, what it cost --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Shipped revenue</p>
            <p class="tabular mt-1 text-xl font-semibold">{{ $money($stats['shipped_revenue']) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Cost of goods shipped</p>
            <p class="tabular mt-1 text-xl font-semibold">{{ $money($stats['shipped_cost']) }}</p>
            <p class="text-xs text-base-content/50">At average purchase cost</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Gross margin</p>
            <p @class(['tabular mt-1 text-xl font-semibold', 'text-error' => $stats['gross_margin'] < 0])>
                {{ $money($stats['gross_margin']) }}
            </p>
            @if ($stats['margin_percent'] !== null)
                <p class="text-xs text-base-content/50">{{ $stats['margin_percent'] }}% of revenue</p>
            @endif
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Trading partners</p>
            <p class="tabular mt-1 text-xl font-semibold">
                {{ number_format($stats['vendors']) }} <span class="text-sm font-normal text-base-content/50">vendors</span>
            </p>
            <p class="text-xs text-base-content/50">{{ number_format($stats['customers']) }} customers</p>
        </div>
    </div>

    {{-- Trend --}}
    <x-card title="Purchases against sales"
            :subtitle="'Order totals over the last ' . $months . ' months, drafts and cancellations excluded'">
        @if (array_sum($trend['purchases']) === 0.0 && array_sum($trend['sales']) === 0.0)
            <p class="py-8 text-center text-sm text-base-content/60">No orders in this period.</p>
        @else
            <div class="h-72"
                 wire:key="trend-{{ $months }}"
                 x-data="chart({
                     type: 'line',
                     currency: @js($currency),
                     data: {
                         labels: @js($trend['labels']),
                         datasets: [
                             { label: 'Purchases', data: @js($trend['purchases']) },
                             { label: 'Sales', data: @js($trend['sales']) },
                         ],
                     },
                 })">
                <canvas x-ref="canvas"></canvas>
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Stock value by category --}}
        <x-card title="Stock value by category" subtitle="Share of on-hand value">
            @if ($byCategory === [])
                <p class="py-8 text-center text-sm text-base-content/60">No stock on hand.</p>
            @else
                <div class="h-72"
                     x-data="chart({
                         type: 'doughnut',
                         currency: @js($currency),
                         data: {
                             labels: @js(array_column($byCategory, 'category')),
                             datasets: [{ data: @js(array_column($byCategory, 'value')) }],
                         },
                     })">
                    <canvas x-ref="canvas"></canvas>
                </div>
            @endif
        </x-card>

        {{-- What needs reordering --}}
        <x-card title="Low stock" :subtitle="$lowStock->isEmpty() ? null : $lowStock->count() . ' material(s) at or below reorder level'">
            @if ($lowStock->isEmpty())
                <p class="py-8 text-center text-sm text-base-content/60">Nothing is below its reorder level.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm">
                        <thead>
                            <tr>
                                <th class="w-24">Code</th>
                                <th>Material</th>
                                <th class="text-right">On hand</th>
                                <th class="text-right">Reorder at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lowStock->take(10) as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('materials.show', $row->id) }}"
                                           class="link-hover link font-mono"
                                           wire:navigate>{{ $row->code }}</a>
                                    </td>
                                    <td>{{ $row->name }}</td>
                                    <td class="tabular text-right text-error">
                                        {{ number_format($row->stock, 2) }}
                                        <span class="text-xs text-base-content/50">{{ $row->uom }}</span>
                                    </td>
                                    <td class="tabular text-right">{{ number_format($row->reorder_level, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($lowStock->count() > 10)
                    <p class="mt-2 text-xs text-base-content/50">
                        Showing 10 of {{ $lowStock->count() }}.
                        <a href="{{ route('materials.index', ['reorder' => 1]) }}" class="link" wire:navigate>
                            See all in Materials
                        </a>
                    </p>
                @endif
            @endif
        </x-card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Top materials by stock value --}}
        <x-card title="Top materials by stock value" subtitle="Highest on-hand value">
            @if ($topStock === [])
                <p class="py-8 text-center text-sm text-base-content/60">No stock on hand.</p>
            @else
                <div class="h-72"
                     x-data="chart({
                         type: 'bar',
                         currency: @js($currency),
                         options: { indexAxis: 'y', plugins: { legend: { display: false } } },
                         data: {
                             labels: @js(array_column($topStock, 'code')),
                             datasets: [{ label: 'Stock value', data: @js(array_column($topStock, 'value')) }],
                         },
                     })">
                    <canvas x-ref="canvas"></canvas>
                </div>
            @endif
        </x-card>

        {{-- Top materials by shipped value --}}
        <x-card title="Top materials by sales" subtitle="Value shipped on completed goods issues">
            @if ($topSold === [])
                <p class="py-8 text-center text-sm text-base-content/60">Nothing has shipped yet.</p>
            @else
                <div class="h-72"
                     x-data="chart({
                         type: 'bar',
                         currency: @js($currency),
                         options: { indexAxis: 'y', plugins: { legend: { display: false } } },
                         data: {
                             labels: @js(array_column($topSold, 'code')),
                             datasets: [{ label: 'Shipped value', data: @js(array_column($topSold, 'value')) }],
                         },
                     })">
                    <canvas x-ref="canvas"></canvas>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Where the open documents are --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ([
            'purchase' => ['Purchase orders', 'purchase-orders.index', 'shopping-cart'],
            'sales' => ['Sales orders', 'sales-orders.index', 'shopping-bag'],
        ] as $key => [$label, $route, $icon])
            <x-card :title="$label . ' by status'" subtitle="Posted orders onwards">
                <x-slot:header>
                    <a href="{{ route($route) }}" class="btn btn-ghost btn-xs">
                        <x-icon :name="$icon" class="size-4" />
                        Open
                    </a>
                </x-slot:header>

                @if ($orderStatus[$key] === [])
                    <p class="py-6 text-center text-sm text-base-content/60">No posted orders.</p>
                @else
                    <div class="space-y-2">
                        @php $max = max(array_column($orderStatus[$key], 'total')); @endphp

                        @foreach ($orderStatus[$key] as $row)
                            <div class="flex items-center gap-3">
                                <span class="w-40 shrink-0 text-sm">{{ $row['status'] }}</span>
                                <progress class="progress progress-primary flex-1"
                                          value="{{ $row['total'] }}" max="{{ $max }}"></progress>
                                <span class="tabular w-10 text-right text-sm font-medium">{{ $row['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
</div>
