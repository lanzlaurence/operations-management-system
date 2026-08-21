@php
    $summary = $this->summary();
    $orders = $this->recentOrders();
    $currency = \App\Models\Preference::get('currency', 'PHP');

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_shipped' => 'badge-warning',
        'fully_shipped' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header :title="$customer->name" :subtitle="$customer->code">
        <x-slot:actions>
            <span @class([
                'badge',
                'badge-neutral' => $customer->status === \App\Enums\RecordStatus::Active,
                'badge-ghost' => $customer->status !== \App\Enums\RecordStatus::Active,
            ])>
                {{ $customer->status->label() }}
            </span>

            @can('customer-edit')
                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="pencil-square" class="size-4" />
                    Edit
                </a>
            @endcan

            <a href="{{ route('customers.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Trading summary --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Sales orders</p>
            <p class="tabular mt-1 text-2xl font-semibold">{{ number_format($summary['orders']) }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Ordered value</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($summary['live_value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Excludes drafts and cancellations</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Open orders</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($summary['open_value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Still awaiting shipment</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Credit limit</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($summary['credit_limit'], 2) }}
            </p>

            @if ($summary['credit_limit'] > 0)
                <progress class="progress mt-2 w-full {{ $summary['credit_used_percent'] >= 90 ? 'progress-error' : 'progress-primary' }}"
                          value="{{ $summary['credit_used_percent'] }}"
                          max="100"></progress>
                <p class="text-xs text-base-content/50">{{ $summary['credit_used_percent'] }}% committed to open orders</p>
            @else
                <p class="text-xs text-base-content/50">No credit limit set</p>
            @endif
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- Record details --}}
        <x-card title="Details">
            <dl class="grid grid-cols-3 gap-y-3 text-sm">
                <dt class="text-base-content/60">Code</dt>
                <dd class="col-span-2 font-mono font-medium">{{ $customer->code }}</dd>

                <dt class="text-base-content/60">Payment terms</dt>
                <dd class="col-span-2">{{ $customer->payment_terms ?: '—' }}</dd>

                <dt class="text-base-content/60">Address</dt>
                <dd class="col-span-2">
                    @php
                        $address = collect([
                            $customer->address_line_1,
                            $customer->address_line_2,
                            $customer->suburb_barangay,
                            $customer->city,
                            $customer->state_province,
                            $customer->postal_code,
                            $customer->country,
                        ])->filter();
                    @endphp

                    {{ $address->isEmpty() ? '—' : $address->implode(', ') }}
                </dd>

                <dt class="text-base-content/60">Created</dt>
                <dd class="col-span-2">{{ $customer->created_at?->format('M d, Y g:i A') }}</dd>

                <dt class="text-base-content/60">Last updated</dt>
                <dd class="col-span-2">{{ $customer->updated_at?->format('M d, Y g:i A') }}</dd>
            </dl>
        </x-card>

        {{-- Contact persons --}}
        <x-card title="Contact persons">
            @if (empty($customer->contact_persons))
                <p class="text-sm text-base-content/60">No contact persons recorded.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($customer->contact_persons as $contact)
                        <li class="rounded-box border border-base-300 p-3">
                            <p class="font-medium">{{ $contact['name'] ?? '—' }}</p>

                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/70">
                                @if (! empty($contact['email']))
                                    <a href="mailto:{{ $contact['email'] }}" class="link-hover link flex items-center gap-1">
                                        <x-icon name="envelope" class="size-4" />
                                        {{ $contact['email'] }}
                                    </a>
                                @endif

                                @if (! empty($contact['phone']))
                                    <a href="tel:{{ $contact['phone'] }}" class="link-hover link flex items-center gap-1">
                                        <x-icon name="phone" class="size-4" />
                                        {{ $contact['phone'] }}
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    {{-- Recent orders --}}
    <x-card title="Recent sales orders" :subtitle="$orders->isEmpty() ? null : 'Latest ' . $orders->count()">
        @if ($orders->isEmpty())
            <p class="text-sm text-base-content/60">This customer has no sales orders yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Status</th>
                            <th>Order date</th>
                            <th>Delivery date</th>
                            <th class="text-right">Grand total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('sales-orders.show', $order->id) }}" class="link-hover link font-mono">
                                        {{ $order->code }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-sm {{ $statusBadge[$order->status->value] ?? 'badge-ghost' }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $order->order_date?->format('M d, Y') }}</td>
                                <td>{{ $order->delivery_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="tabular text-right">
                                    {{ $currency }} {{ number_format((float) $order->grand_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-entity-log :logs="$customer->logs" />
</div>
