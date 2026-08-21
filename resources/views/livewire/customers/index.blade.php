@php
    $options = $this->filterOptions();
    $orderCounts = $this->orderCounts();
    $currency = \App\Models\Preference::get('currency', 'PHP');
@endphp

<div class="space-y-4">
    <x-page-header title="Customers" subtitle="Who the business sells to, with their terms and credit limits">
        <x-slot:actions>
            @can('customer-create')
                <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="plus" class="size-4" />
                    Add Customer
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search code, name, city or terms…"
             empty-title="No customers"
             empty-message="Add one before raising a sales order.">

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

        @foreach ($records as $customer)
            <tr wire:key="customer-{{ $customer->id }}">
                <td>
                    <a href="{{ route('customers.show', $customer) }}"
                       class="link-hover link font-mono font-medium"
                       wire:navigate>
                        {{ $customer->code }}
                    </a>
                </td>
                <td>
                    <div class="font-medium">{{ $customer->name }}</div>
                    @if ($customer->contact_persons)
                        <div class="text-xs text-base-content/60">
                            {{ $customer->contact_persons[0]['name'] ?? '' }}
                            @if (count($customer->contact_persons) > 1)
                                <span class="text-base-content/40">+{{ count($customer->contact_persons) - 1 }} more</span>
                            @endif
                        </div>
                    @endif
                </td>
                <td class="text-base-content/70">
                    {{ collect([$customer->city, $customer->state_province])->filter()->implode(', ') ?: '—' }}
                </td>
                <td class="text-base-content/70">{{ $customer->payment_terms ?: '—' }}</td>
                <td class="tabular text-right">
                    {{ $currency }} {{ number_format((float) $customer->credit_amount, 2) }}
                </td>
                <td class="tabular text-right">{{ number_format($orderCounts[$customer->id] ?? 0) }}</td>
                <td>
                    <span @class([
                        'badge badge-sm',
                        'badge-neutral' => $customer->status === \App\Enums\RecordStatus::Active,
                        'badge-ghost' => $customer->status !== \App\Enums\RecordStatus::Active,
                    ])>
                        {{ $customer->status->label() }}
                    </span>
                </td>
                <td>
                    <div class="flex justify-end gap-1">
                        <a href="{{ route('customers.show', $customer) }}" class="btn btn-ghost btn-xs" wire:navigate>
                            <x-icon name="eye" class="size-4" />
                            View
                        </a>

                        @can('customer-edit')
                            <button type="button"
                                    class="btn btn-ghost btn-xs"
                                    wire:click="toggleStatus({{ $customer->id }})"
                                    title="{{ $customer->status === \App\Enums\RecordStatus::Active ? 'Set inactive' : 'Set active' }}">
                                <x-icon :name="$customer->status === \App\Enums\RecordStatus::Active ? 'pause-circle' : 'play-circle'"
                                        class="size-4" />
                            </button>

                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-ghost btn-xs" wire:navigate>
                                <x-icon name="pencil-square" class="size-4" />
                                Edit
                            </a>
                        @endcan

                        @can('customer-delete')
                            <button type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="confirmDelete({{ $customer->id }})">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-modal :name="$this->modalName()" title="Delete customer?">
        <p class="text-sm text-base-content/70">
            @if ($customer = $this->deletingRecord())
                This deletes <span class="font-semibold">{{ $customer->code }} — {{ $customer->name }}</span>.
                Customers with sales orders cannot be deleted; set them inactive instead.
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
