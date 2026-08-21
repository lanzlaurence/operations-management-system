@php
    $options = $this->filterOptions();
    $currency = \App\Models\Preference::get('currency', 'PHP');
@endphp

<div class="space-y-4">
    <x-page-header title="Charges" subtitle="Taxes, fees and discounts applied to orders">
        <x-slot:actions>
            @can('charge-create')
                <a href="{{ route('charges.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Charge
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search name or description…"
             empty-title="No charges"
             empty-message="Add one to apply taxes, fees or discounts on orders.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-36" wire:model.live="typeFilter">
                <option value="">All types</option>
                @foreach ($options['types'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-36" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach ($options['statuses'] as $value => $label)
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
            <x-table.heading field="name" width="220px">Name</x-table.heading>
            <x-table.heading>Description</x-table.heading>
            <x-table.heading field="type" width="120px">Type</x-table.heading>
            <x-table.heading field="value" align="right" width="140px">Value</x-table.heading>
            <x-table.heading field="status" width="110px">Status</x-table.heading>
            <x-table.heading align="right" width="150px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $charge)
            <tr wire:key="charge-{{ $charge->id }}">
                <td class="font-medium">{{ $charge->name }}</td>
                <td class="text-base-content/70">{{ $charge->description ?: '—' }}</td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-warning' => $charge->type === \App\Enums\ChargeType::Tax,
                        'badge-success' => $charge->type === \App\Enums\ChargeType::Discount,
                    ])>
                        {{ $charge->type->label() }}
                    </span>
                </td>
                <td class="tabular text-right">
                    @if ($charge->value_type === \App\Enums\ChargeValueType::Percentage)
                        {{ rtrim(rtrim(number_format((float) $charge->value, 2), '0'), '.') }}%
                    @else
                        {{ $currency }} {{ number_format((float) $charge->value, 2) }}
                    @endif
                </td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-neutral' => $charge->status === \App\Enums\RecordStatus::Active,
                        'badge-ghost' => $charge->status !== \App\Enums\RecordStatus::Active,
                    ])>
                        {{ $charge->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        @can('charge-edit')
                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    wire:click="toggleStatus({{ $charge->id }})"
                                    title="{{ $charge->status === \App\Enums\RecordStatus::Active ? 'Set inactive' : 'Set active' }}">
                                <x-icon :name="$charge->status === \App\Enums\RecordStatus::Active ? 'pause-circle' : 'play-circle'"
                                        class="size-4" />
                            </button>

                            <a href="{{ route('charges.edit', $charge) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('charge-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $charge->id }})">
                                <x-icon name="trash" class="size-4" />
                                Delete
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete charge?">
        <p class="text-sm text-base-content/70">
            @if ($charge = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $charge->name }}</span>. Orders that already carry it keep
                their own snapshot; a charge in use has to be set inactive instead.
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
