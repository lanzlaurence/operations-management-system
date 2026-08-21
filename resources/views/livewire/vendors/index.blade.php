@php
    $options = $this->filterOptions();
    $orderCounts = $this->orderCounts();
    $currency = \App\Models\Preference::get('currency', 'PHP');
@endphp

<div class="space-y-4">
    <x-page-header title="Vendors" subtitle="Who the business buys from, with their terms and credit limits">
        <x-slot:actions>
            @can('vendor-create')
                <a href="{{ route('vendors.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Vendor
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search code, name, city or terms…"
             empty-title="No vendors"
             empty-message="Add one before raising a purchase order.">

        <x-slot:toolbar>
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
            <x-table.heading field="code" width="120px">Code</x-table.heading>
            <x-table.heading field="name">Name</x-table.heading>
            <x-table.heading field="city" width="180px">Location</x-table.heading>
            <x-table.heading field="payment_terms" width="130px">Terms</x-table.heading>
            <x-table.heading field="credit_amount" align="right" width="150px">Credit limit</x-table.heading>
            <x-table.heading align="right" width="90px">Orders</x-table.heading>
            <x-table.heading field="status" width="110px">Status</x-table.heading>
            <x-table.heading align="right" width="190px">Actions</x-table.heading>
        </x-slot:head>

        @foreach ($records as $vendor)
            <tr wire:key="vendor-{{ $vendor->id }}">
                <td>
                    <a href="{{ route('vendors.show', $vendor) }}"
                       class="link-hover link font-mono font-medium"
                       wire:navigate>
                        {{ $vendor->code }}
                    </a>
                </td>
                <td>
                    <div class="font-medium">{{ $vendor->name }}</div>
                    @if ($vendor->contact_persons)
                        <div class="text-xs text-base-content/60">
                            {{ $vendor->contact_persons[0]['name'] ?? '' }}
                            @if (count($vendor->contact_persons) > 1)
                                <span class="text-base-content/40">+{{ count($vendor->contact_persons) - 1 }} more</span>
                            @endif
                        </div>
                    @endif
                </td>
                <td class="text-base-content/70">
                    {{ collect([$vendor->city, $vendor->state_province])->filter()->implode(', ') ?: '—' }}
                </td>
                <td class="text-base-content/70">{{ $vendor->payment_terms ?: '—' }}</td>
                <td class="tabular text-right">
                    {{ $currency }} {{ number_format((float) $vendor->credit_amount, 2) }}
                </td>
                <td class="tabular text-right">{{ number_format($orderCounts[$vendor->id] ?? 0) }}</td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-neutral' => $vendor->status === \App\Enums\RecordStatus::Active,
                        'badge-ghost' => $vendor->status !== \App\Enums\RecordStatus::Active,
                    ])>
                        {{ $vendor->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                            View
                        </a>

                        @can('vendor-edit')
                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    wire:click="toggleStatus({{ $vendor->id }})"
                                    title="{{ $vendor->status === \App\Enums\RecordStatus::Active ? 'Set inactive' : 'Set active' }}">
                                <x-icon :name="$vendor->status === \App\Enums\RecordStatus::Active ? 'pause-circle' : 'play-circle'"
                                        class="size-4" />
                            </button>

                            <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('vendor-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $vendor->id }})">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete vendor?">
        <p class="text-sm text-base-content/70">
            @if ($vendor = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $vendor->code }} — {{ $vendor->name }}</span>.
                Vendors with purchase orders cannot be deleted; set them inactive instead.
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
