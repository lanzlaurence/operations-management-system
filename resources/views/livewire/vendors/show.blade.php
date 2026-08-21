@php
    $summary = $this->summary();
    $orders = $this->recentOrders();
    $materials = $this->suppliedMaterials();
    $currency = \App\Models\Preference::get('currency', 'PHP');

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_received' => 'badge-warning',
        'fully_received' => 'badge-success',
        'cancelled' => 'badge-error',
    ];
@endphp

<div class="space-y-4">
    <x-page-header :title="$vendor->name" :subtitle="$vendor->code">
        <x-slot:actions>
            <span @class([
                'badge',
                'badge-neutral' => $vendor->status === \App\Enums\RecordStatus::Active,
                'badge-ghost' => $vendor->status !== \App\Enums\RecordStatus::Active,
            ])>
                {{ $vendor->status->label() }}
            </span>

            @can('vendor-edit')
                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-primary btn-sm" wire:navigate>
                    <x-icon name="pencil-square" class="size-4" />
                    Edit
                </a>
            @endcan

            <a href="{{ route('vendors.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Purchasing summary --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Purchase orders</p>
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
            <p class="text-sm text-base-content/60">Awaiting delivery</p>
            <p class="tabular mt-1 text-2xl font-semibold">
                {{ $currency }} {{ number_format($summary['open_value'], 2) }}
            </p>
            <p class="text-xs text-base-content/50">Ordered but not yet fully received</p>
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
                <dd class="col-span-2 font-mono font-medium">{{ $vendor->code }}</dd>

                <dt class="text-base-content/60">Payment terms</dt>
                <dd class="col-span-2">{{ $vendor->payment_terms ?: '—' }}</dd>

                <dt class="text-base-content/60">Address</dt>
                <dd class="col-span-2">
                    @php
                        $address = collect([
                            $vendor->address_line_1,
                            $vendor->address_line_2,
                            $vendor->suburb_barangay,
                            $vendor->city,
                            $vendor->state_province,
                            $vendor->postal_code,
                            $vendor->country,
                        ])->filter();
                    @endphp

                    {{ $address->isEmpty() ? '—' : $address->implode(', ') }}
                </dd>

                <dt class="text-base-content/60">Created</dt>
                <dd class="col-span-2">{{ $vendor->created_at?->format('M d, Y g:i A') }}</dd>

                <dt class="text-base-content/60">Last updated</dt>
                <dd class="col-span-2">{{ $vendor->updated_at?->format('M d, Y g:i A') }}</dd>
            </dl>
        </x-card>

        {{-- Contact persons --}}
        <x-card title="Contact persons">
            @if (empty($vendor->contact_persons))
                <p class="text-sm text-base-content/60">No contact persons recorded.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($vendor->contact_persons as $contact)
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

    {{-- What this vendor supplies --}}
    <x-card title="Materials supplied" subtitle="From posted orders onwards, most ordered first">
        @if ($materials->isEmpty())
            <p class="text-sm text-base-content/60">Nothing has been ordered from this vendor yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th class="w-28">Code</th>
                            <th>Material</th>
                            <th class="text-right">Qty ordered</th>
                            <th class="text-right">Avg unit cost</th>
                            <th>Last ordered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($materials as $material)
                            <tr>
                                <td>
                                    <a href="{{ route('materials.show', $material->id) }}" class="link-hover link font-mono">
                                        {{ $material->code }}
                                    </a>
                                </td>
                                <td>{{ $material->name }}</td>
                                <td class="tabular text-right">
                                    {{ number_format((float) $material->qty_ordered, 2) }}
                                </td>
                                <td class="tabular text-right">
                                    {{ $currency }} {{ number_format((float) $material->avg_unit_cost, 2) }}
                                </td>
                                <td>{{ \Illuminate\Support\Carbon::parse($material->last_ordered_at)->format('M d, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Recent orders --}}
    <x-card title="Recent purchase orders" :subtitle="$orders->isEmpty() ? null : 'Latest ' . $orders->count()">
        @if ($orders->isEmpty())
            <p class="text-sm text-base-content/60">This vendor has no purchase orders yet.</p>
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
                                    <a href="{{ route('purchase-orders.show', $order->id) }}" class="link-hover link font-mono">
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

    <x-entity-log :logs="$vendor->logs" />
</div>
