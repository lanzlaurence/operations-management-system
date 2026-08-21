@php
    $stock = $this->stockSummary();
    $locations = $this->stockByLocation();
    $costing = $this->costing();
    $currency = \App\Models\Preference::get('currency', 'PHP');
    $uom = $material->uom?->acronym;
@endphp

<div class="space-y-4">
    <x-page-header :title="$material->name" :subtitle="$material->code . ($material->sku ? ' · SKU ' . $material->sku : '')">
        <x-slot:actions>
            <span @class([
                'badge',
                'badge-neutral' => $material->status === \App\Enums\RecordStatus::Active,
                'badge-ghost' => $material->status !== \App\Enums\RecordStatus::Active,
            ])>
                {{ $material->status->label() }}
            </span>

            <a href="{{ route('materials.purchase-history', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-down-tray" class="size-4" />
                Purchases
            </a>

            <a href="{{ route('materials.sales-history', $material) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-up-tray" class="size-4" />
                Sales
            </a>

            @can('material-edit')
                <a href="{{ route('materials.edit', $material) }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="pencil-square" class="size-4" />
                    Edit
                </a>
            @endcan

            <a href="{{ route('materials.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Stock position --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">On hand</p>
            <p @class(['tabular mt-1 text-2xl font-semibold', 'text-error' => $stock['needs_reorder']])>
                {{ number_format($stock['on_hand'], 2) }}
                <span class="text-sm font-normal text-base-content/50">{{ $uom }}</span>
            </p>

            @if ($stock['needs_reorder'])
                <p class="text-xs text-error">At or below the reorder level of {{ (int) $material->reorder_level }}</p>
            @elseif ($material->reorder_level > 0)
                <p class="text-xs text-base-content/50">Reorder at {{ (int) $material->reorder_level }}</p>
            @endif
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Stock value</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($stock['value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">At average purchase cost</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Locations holding it</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($stock['locations']) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Against maximum</p>

            @if ($stock['capacity_percent'] === null)
                <p class="mt-1 text-2xl font-semibold">—</p>
                <p class="text-xs text-base-content/50">No maximum level set</p>
            @else
                <p class="tabular mt-1 text-2xl font-semibold">{{ $stock['capacity_percent'] }}%</p>
                <progress class="progress progress-primary mt-2 w-full"
                          value="{{ $stock['capacity_percent'] }}" max="100"></progress>
                <p class="text-xs text-base-content/50">Max {{ (int) $material->max_stock_level }} {{ $uom }}</p>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Costing: maintained vs actual --}}
        <x-card title="Costing" subtitle="List values against what the movements actually produced">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="text-right">List</th>
                            <th class="text-right">Average</th>
                            <th class="text-right">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="font-medium">Purchase cost</td>
                            <td class="tabular text-right">{{ number_format($costing['list_cost'], 2) }}</td>
                            <td class="tabular text-right">{{ number_format($costing['avg_cost'], 2) }}</td>
                            <td @class(['tabular text-right', 'text-error' => $costing['cost_variance'] > 0])>
                                {{ $costing['cost_variance'] > 0 ? '+' : '' }}{{ number_format($costing['cost_variance'], 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="font-medium">Selling price</td>
                            <td class="tabular text-right">{{ number_format($costing['list_price'], 2) }}</td>
                            <td class="tabular text-right">{{ number_format($costing['avg_price'], 2) }}</td>
                            <td @class(['tabular text-right', 'text-error' => $costing['price_variance'] < 0])>
                                {{ $costing['price_variance'] > 0 ? '+' : '' }}{{ number_format($costing['price_variance'], 2) }}
                            </td>
                        </tr>
                        <tr class="border-t-2">
                            <td class="font-medium">Margin</td>
                            <td class="tabular text-right">{{ number_format($costing['list_margin'], 2) }}</td>
                            <td @class(['tabular text-right', 'text-error' => $costing['actual_margin'] < 0])>
                                {{ number_format($costing['actual_margin'], 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-2 text-xs text-base-content/50">
                Averages are weighted by quantity across completed receipts and issues, and fall back to the list value
                when nothing has moved yet.
            </p>
        </x-card>

        {{-- Where the stock sits --}}
        <x-card title="Stock by location">
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
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($locations as $row)
                                <tr>
                                    <td class="font-mono">{{ $row->code }}</td>
                                    <td>{{ $row->name }}</td>
                                    <td class="tabular text-right">{{ number_format($row->quantity, 2) }}</td>
                                    <td class="tabular text-right">{{ number_format($row->value, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    {{-- Record details --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Details">
            <dl class="grid grid-cols-3 gap-y-3 text-sm">
                <dt class="text-base-content/60">Code</dt>
                <dd class="col-span-2 font-mono font-medium">{{ $material->code }}</dd>

                <dt class="text-base-content/60">SKU</dt>
                <dd class="col-span-2 font-mono">{{ $material->sku ?: '—' }}</dd>

                <dt class="text-base-content/60">Category</dt>
                <dd class="col-span-2">{{ $material->category?->name ?: '—' }}</dd>

                <dt class="text-base-content/60">Brand</dt>
                <dd class="col-span-2">{{ $material->brand?->name ?: '—' }}</dd>

                <dt class="text-base-content/60">Unit</dt>
                <dd class="col-span-2">
                    {{ $material->uom?->acronym ?: '—' }}
                    @if ($material->uom?->description)
                        <span class="text-base-content/50">({{ $material->uom->description }})</span>
                    @endif
                </dd>

                <dt class="text-base-content/60">Tracking</dt>
                <dd class="col-span-2">
                    @php
                        $tracking = collect([
                            $material->track_serial_number ? 'Serial numbers' : null,
                            $material->track_batch_number ? 'Batch numbers' : null,
                        ])->filter();
                    @endphp

                    {{ $tracking->isEmpty() ? 'None' : $tracking->implode(', ') }}
                </dd>

                <dt class="text-base-content/60">Description</dt>
                <dd class="col-span-2">{{ $material->description ?: '—' }}</dd>

                <dt class="text-base-content/60">Created</dt>
                <dd class="col-span-2">{{ $material->created_at?->format('M d, Y g:i A') }}</dd>
            </dl>
        </x-card>

        <x-card title="Stock levels and dimensions">
            <div class="grid gap-4 sm:grid-cols-2">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Minimum</dt>
                        <dd class="tabular">{{ (int) $material->min_stock_level }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Reorder level</dt>
                        <dd class="tabular">{{ (int) $material->reorder_level }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Maximum</dt>
                        <dd class="tabular">{{ (int) $material->max_stock_level }}</dd>
                    </div>
                </dl>

                <dl class="space-y-2 text-sm">
                    @foreach ([
                        'Weight' => $material->weight,
                        'Length' => $material->length,
                        'Width' => $material->width,
                        'Height' => $material->height,
                        'Volume' => $material->volume,
                    ] as $label => $value)
                        <div class="flex justify-between">
                            <dt class="text-base-content/60">{{ $label }}</dt>
                            <dd class="tabular">{{ $value === null ? '—' : number_format((float) $value, 2) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </x-card>
    </div>

    <x-entity-log :logs="$material->logs" />
</div>
