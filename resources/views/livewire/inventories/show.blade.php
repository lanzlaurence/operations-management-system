@php
    $summary = $this->summary();
    $flow = $this->flow();
    $currency = \App\Models\Preference::get('currency', 'PHP');
    $uom = $inventory->material?->uom?->acronym;
@endphp

<div class="space-y-4">
    <x-page-header :title="$inventory->material?->name"
                   :subtitle="$inventory->code . ' · ' . $inventory->location?->name">
        <x-slot:actions>
            <a href="{{ route('materials.show', $inventory->material_id) }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="cube" class="size-4" />
                Material
            </a>

            @can('inventory-adjust')
                <a href="{{ route('inventories.manual-adjustment') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="adjustments-horizontal" class="size-4" />
                    Adjust
                </a>
            @endcan

            <a href="{{ route('inventories.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Balance and reconciliation --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">On hand</p>
            <p @class(['tabular mt-1 text-2xl font-semibold', 'text-error' => $summary['needs_reorder']])>
                {{ number_format($summary['on_hand'], 2) }}
                <span class="text-sm font-normal text-base-content/50">{{ $uom }}</span>
            </p>

            @if ($summary['needs_reorder'])
                <p class="text-xs text-error">At or below reorder level {{ $summary['reorder_level'] }}</p>
            @endif
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Value</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($summary['value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">At average purchase cost</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Total in / out</p>
            <p class="tabular mt-1 text-lg font-semibold">
                <span class="text-success">+{{ number_format($flow['inbound'], 2) }}</span>
                <span class="text-base-content/40">/</span>
                <span class="text-error">−{{ number_format($flow['outbound'], 2) }}</span>
            </p>
            <p class="text-xs text-base-content/50">{{ number_format($summary['movements']) }} movements</p>
        </div>

        {{-- The ledger must add up to the balance; if it does not, something
             wrote a quantity outside InventoryService. --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Ledger check</p>

            @if ($summary['reconciled'])
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold text-success">
                    <x-icon name="check-circle" class="size-5" />
                    Reconciled
                </p>
                <p class="text-xs text-base-content/50">Movements sum to the balance</p>
            @else
                <p class="mt-1 flex items-center gap-2 text-lg font-semibold text-error">
                    <x-icon name="exclamation-triangle" class="size-5" />
                    Mismatch
                </p>
                <p class="text-xs text-error">
                    Ledger totals {{ number_format($summary['ledger_total'], 2) }} against a balance of
                    {{ number_format($summary['on_hand'], 2) }}
                </p>
            @endif
        </div>
    </div>

    {{-- Record details --}}
    <x-card title="Record">
        <dl class="grid gap-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-base-content/60">Material</dt>
                <dd class="mt-0.5 font-medium">{{ $inventory->material?->name }}</dd>
                <dd class="font-mono text-xs text-base-content/50">{{ $inventory->material?->code }}</dd>
            </div>

            <div>
                <dt class="text-base-content/60">Classification</dt>
                <dd class="mt-0.5">{{ $inventory->material?->category?->name ?: '—' }}</dd>
                <dd class="text-xs text-base-content/50">{{ $inventory->material?->brand?->name }}</dd>
            </div>

            <div>
                <dt class="text-base-content/60">Location</dt>
                <dd class="mt-0.5 font-medium">{{ $inventory->location?->name }}</dd>
                <dd class="font-mono text-xs text-base-content/50">{{ $inventory->location?->code }}</dd>
            </div>

            <div>
                <dt class="text-base-content/60">Unit</dt>
                <dd class="mt-0.5">{{ $uom ?: '—' }}</dd>
                <dd class="text-xs text-base-content/50">
                    Avg cost {{ number_format((float) ($inventory->material->avg_unit_cost ?? 0), 2) }}
                </dd>
            </div>
        </dl>
    </x-card>

    {{-- Movement ledger --}}
    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search movement code or remarks…"
             empty-title="No movements"
             empty-message="Nothing has moved through this record yet.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-48" wire:model.live="typeFilter">
                <option value="">All movement types</option>
                @foreach ($typeOptions as $value => $label)
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
            <x-table.heading field="created_at" width="150px">Date</x-table.heading>
            <x-table.heading field="movement_code" width="120px">Movement</x-table.heading>
            <x-table.heading width="160px">Type</x-table.heading>
            <x-table.heading align="right" width="110px">Before</x-table.heading>
            <x-table.heading field="quantity_change" align="right" width="110px">Change</x-table.heading>
            <x-table.heading field="quantity_after" align="right" width="110px">After</x-table.heading>
            <x-table.heading width="150px">Counterparty</x-table.heading>
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
                <td class="tabular text-right text-base-content/60">{{ number_format((float) $log->quantity_before, 2) }}</td>
                <td class="tabular text-right font-medium {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }}
                </td>
                <td class="tabular text-right font-medium">{{ number_format((float) $log->quantity_after, 2) }}</td>
                <td class="text-sm">
                    {{ $log->transferLocation?->name ?? '—' }}
                </td>
                <td class="text-sm text-base-content/70">{{ $log->remarks ?: '—' }}</td>
                <td class="text-sm">{{ $log->user?->name ?? 'System' }}</td>
            </tr>
        @endforeach
    </x-table>
</div>
