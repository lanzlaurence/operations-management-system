@php
    $totals = $this->totals();
@endphp

<div class="space-y-4">
    <x-page-header title="Inventory log" subtitle="Every stock movement, across all materials and locations">
        <x-slot:actions>
            @can('activity-transaction-log')
                <a href="{{ route('activity.transaction-log') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="document-text" class="size-4" />
                    Transaction log
                </a>
            @endcan

            @can('inventory-view')
                <a href="{{ route('inventories.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="building-storefront" class="size-4" />
                    Balances
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Volume for everything the filters match --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Movements</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($totals['movements']) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Quantity in</p>
            <p class="tabular mt-1 text-2xl font-semibold text-success">+{{ number_format($totals['inbound'], 2) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Quantity out</p>
            <p class="tabular mt-1 text-2xl font-semibold text-error">−{{ number_format($totals['outbound'], 2) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Net movement</p>
            <p @class(['tabular mt-1 text-2xl font-semibold', 'text-error' => $totals['net'] < 0])>
                {{ $totals['net'] > 0 ? '+' : '' }}{{ number_format($totals['net'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Across mixed units of measurement</p>
        </div>
    </div>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search movement code, material, stock code or remarks…"
             empty-title="No movements"
             empty-message="Nothing matches the current filters.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-36" wire:model.live="moduleFilter">
                <option value="">All modules</option>
                @foreach ($moduleOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-44" wire:model.live="typeFilter">
                <option value="">All movement types</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-40" wire:model.live="locationFilter">
                <option value="">All locations</option>
                @foreach ($locations as $id => $name)
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
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="created_at" width="160px">Date &amp; time</x-table.heading>
            <x-table.heading field="movement_code" width="120px">Movement</x-table.heading>
            <x-table.heading field="type" width="160px">Type</x-table.heading>
            <x-table.heading>Material</x-table.heading>
            <x-table.heading width="150px">Location</x-table.heading>
            <x-table.heading align="right" width="105px">Before</x-table.heading>
            <x-table.heading field="quantity_change" align="right" width="105px">Change</x-table.heading>
            <x-table.heading field="quantity_after" align="right" width="105px">After</x-table.heading>
            <x-table.heading>Remarks</x-table.heading>
            <x-table.heading width="130px">By</x-table.heading>
        </x-slot:head>

        @foreach ($records as $log)
            @php $change = (float) $log->quantity_change; @endphp

            <tr wire:key="movement-{{ $log->id }}">
                <td class="whitespace-nowrap text-sm">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                <td class="font-mono text-sm">{{ $log->movement_code }}</td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-success' => $log->type->isInbound(),
                        'badge-error' => $log->type->isOutbound(),
                        'badge-ghost' => ! $log->type->isInbound() && ! $log->type->isOutbound(),
                    ])>
                        {{ $log->type->label() }}
                    </span>
                    <div class="text-xs text-base-content/50">{{ $log->type->module() }}</div>
                </td>
                <td>
                    <a href="{{ route('materials.show', $log->material_id) }}"
                       class="link-hover link font-medium"
                       wire:navigate>
                        {{ $log->material?->name }}
                    </a>
                    <div class="text-xs text-base-content/50">
                        <span class="font-mono">{{ $log->material?->code }}</span>
                        @if ($log->inventory)
                            ·
                            <a href="{{ route('inventories.show', $log->inventory_id) }}"
                               class="link-hover link font-mono"
                               wire:navigate>{{ $log->inventory->code }}</a>
                        @endif
                    </div>
                </td>
                <td class="text-sm">
                    {{ $log->location?->name }}

                    @if ($log->transferLocation)
                        <div class="text-xs text-base-content/50">
                            {{ $log->type->isInbound() ? 'from' : 'to' }} {{ $log->transferLocation->name }}
                        </div>
                    @endif
                </td>
                <td class="tabular text-right text-base-content/60">{{ number_format((float) $log->quantity_before, 2) }}</td>
                <td class="tabular text-right font-medium {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }}
                </td>
                <td class="tabular text-right font-medium">{{ number_format((float) $log->quantity_after, 2) }}</td>
                <td class="text-sm text-base-content/70">{{ $log->remarks ?: '—' }}</td>
                <td class="text-sm">{{ $log->user?->name ?? 'System' }}</td>
            </tr>
        @endforeach
    </x-table>
</div>
