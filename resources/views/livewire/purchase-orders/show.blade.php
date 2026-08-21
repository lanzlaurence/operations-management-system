@php
    $order = $purchaseOrder;
    $actions = $this->actions();
    $outstanding = $this->outstanding();
    $quantities = $this->quantities();
    $percent = $this->receivedPercent();

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_received' => 'badge-warning',
        'fully_received' => 'badge-success',
        'cancelled' => 'badge-error',
    ];

    $receiptBadge = [
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error',
    ];

    // What each confirmation should say before it is committed.
    $prompts = [
        'post' => [
            'Post this purchase order?',
            'Posting releases the order to the vendor and allows goods receipts to be raised against it. The lines can no longer be edited.',
            'Post order',
            'btn-primary',
        ],
        'cancel' => [
            'Cancel this purchase order?',
            'Every goods receipt raised against it is cancelled too, and any stock they booked in is reversed out. This is refused if that stock has since been issued.',
            'Cancel order',
            'btn-error',
        ],
        'revert' => [
            'Revert this order to draft?',
            'The order becomes editable again. This is only allowed while no receipt has booked stock; cancelled receipts are reopened as pending.',
            'Revert to draft',
            'btn-warning',
        ],
        'delete' => [
            'Delete this purchase order?',
            'The draft and any receipts prepared against it are removed. Nothing that received stock can be deleted.',
            'Delete order',
            'btn-error',
        ],
    ];
@endphp

<div class="space-y-4">
    <x-page-header :title="$order->code"
                   :subtitle="$order->vendor?->name . ($order->reference_no ? ' · ' . $order->reference_no : '')">
        <x-slot:actions>
            <span class="badge {{ $statusBadge[$order->status->value] ?? 'badge-ghost' }}">
                {{ $order->status->label() }}
            </span>

            @if ($actions['receive'])
                @can('goods-receipt-create')
                    <a href="{{ route('purchase-orders.goods-receipts.create', $order) }}"
                       class="btn btn-primary btn-sm" wire:navigate>
                        <x-icon name="archive-box-arrow-down" class="size-4" />
                        Receive
                    </a>
                @endcan
            @endif

            @if ($actions['post'])
                @can('purchase-order-post')
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirm('post')">
                        <x-icon name="paper-airplane" class="size-4" />
                        Post
                    </button>
                @endcan
            @endif

            @if ($actions['edit'])
                @can('purchase-order-edit')
                    <a href="{{ route('purchase-orders.edit', $order) }}" class="btn btn-ghost btn-sm" wire:navigate>
                        <x-icon name="pencil-square" class="size-4" />
                        Edit
                    </a>
                @endcan
            @endif

            {{-- Less common actions kept out of the way --}}
            @if ($actions['revert'] || $actions['cancel'] || $actions['delete'])
                <div class="dropdown dropdown-end">
                    <button type="button" class="btn btn-ghost btn-sm">
                        <x-icon name="ellipsis-vertical" class="size-4" />
                    </button>

                    <ul class="menu dropdown-content z-50 w-52 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
                        @if ($actions['revert'])
                            @can('purchase-order-revert')
                                <li>
                                    <button type="button" wire:click="confirm('revert')">
                                        <x-icon name="arrow-uturn-left" class="size-4" />
                                        Revert to draft
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['cancel'])
                            @can('purchase-order-cancel')
                                <li>
                                    <button type="button" class="text-error" wire:click="confirm('cancel')">
                                        <x-icon name="x-circle" class="size-4" />
                                        Cancel order
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['delete'])
                            @can('purchase-order-delete')
                                <li>
                                    <button type="button" class="text-error" wire:click="confirm('delete')">
                                        <x-icon name="trash" class="size-4" />
                                        Delete order
                                    </button>
                                </li>
                            @endcan
                        @endif
                    </ul>
                </div>
            @endif

            <a href="{{ route('purchase-orders.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Header facts --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Vendor</p>
            <a href="{{ route('vendors.show', $order->vendor_id) }}" class="link-hover link font-medium" wire:navigate>
                {{ $order->vendor?->name }}
            </a>
            <p class="text-xs text-base-content/50">
                {{ $order->vendor?->code }} · {{ $order->vendor?->payment_terms ?: 'terms not set' }}
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Dates</p>
            <p class="font-medium">{{ $order->order_date?->format('M d, Y') }}</p>
            <p class="text-xs text-base-content/50">
                Due {{ $order->delivery_date?->format('M d, Y') ?? 'not set' }}
            </p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Received</p>
            <p class="tabular font-medium">
                {{ number_format($quantities['received'], 2) }} of {{ number_format($quantities['ordered'], 2) }}
            </p>
            @unless ($order->status->isCancelled())
                <progress class="progress progress-primary mt-1 w-full" value="{{ $percent }}" max="100"></progress>
            @endunless
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Grand total</p>
            <p class="tabular text-xl font-semibold">
                {{ $currency }} {{ number_format((float) $order->grand_total, 2) }}
            </p>
            <p class="text-xs text-base-content/50">Raised by {{ $order->user?->name ?? 'system' }}</p>
        </div>
    </div>

    {{-- Lines --}}
    <x-card title="Items">
        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Material</th>
                        <th class="text-right">Ordered</th>
                        <th class="text-right">Received</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Unit cost</th>
                        <th class="text-right">Net</th>
                        <th class="text-right">VAT</th>
                        <th class="text-right">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="text-base-content/50">{{ $item->line_number }}</td>
                            <td>
                                <a href="{{ route('materials.show', $item->material_id) }}"
                                   class="link-hover link font-medium" wire:navigate>
                                    {{ $item->material?->name }}
                                </a>
                                <div class="font-mono text-xs text-base-content/50">{{ $item->material?->code }}</div>
                                @if ($item->remarks)
                                    <div class="text-xs text-base-content/60">{{ $item->remarks }}</div>
                                @endif
                            </td>
                            <td class="tabular text-right">
                                {{ number_format((float) $item->qty_ordered, 2) }}
                                <span class="text-xs text-base-content/50">{{ $item->material?->uom?->acronym }}</span>
                            </td>
                            <td class="tabular text-right">{{ number_format((float) $item->qty_received, 2) }}</td>
                            <td class="tabular text-right">
                                @php $left = $outstanding[$item->id] ?? 0; @endphp
                                <span @class(['text-base-content/40' => $left <= 0])>
                                    {{ number_format($left, 2) }}
                                </span>
                            </td>
                            <td class="tabular text-right">
                                {{ number_format((float) $item->unit_cost_after_discount, 2) }}
                                @if ((float) $item->discount_amount > 0)
                                    <div class="text-xs text-base-content/50">
                                        from {{ number_format((float) $item->unit_cost, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="tabular text-right">{{ number_format((float) $item->net_price, 2) }}</td>
                            <td class="tabular text-right">
                                {{ number_format((float) $item->vat_price, 2) }}
                                @if ($item->is_vatable)
                                    <div class="text-xs text-base-content/50">
                                        {{ $item->vat_type?->label() }} {{ rtrim(rtrim(number_format((float) $item->vat_rate, 2), '0'), '.') }}%
                                    </div>
                                @endif
                            </td>
                            <td class="tabular text-right font-medium">{{ number_format((float) $item->gross_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="mt-4 flex justify-end">
            <dl class="w-full max-w-sm space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-base-content/60">Net</dt>
                    <dd class="tabular">{{ number_format((float) $order->total_net_price, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-base-content/60">VAT</dt>
                    <dd class="tabular">{{ number_format((float) $order->total_vat, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-base-content/60">Gross</dt>
                    <dd class="tabular">{{ number_format((float) $order->total_gross, 2) }}</dd>
                </div>

                @if ((float) $order->header_discount_total > 0)
                    <div class="flex justify-between text-error">
                        <dt>
                            Order discount
                            @if ($order->discount_type)
                                <span class="text-xs">
                                    ({{ $order->discount_type === \App\Enums\DiscountType::Percentage
                                        ? rtrim(rtrim(number_format((float) $order->discount_amount, 2), '0'), '.') . '%'
                                        : $currency . ' ' . number_format((float) $order->discount_amount, 2) }})
                                </span>
                            @endif
                        </dt>
                        <dd class="tabular">−{{ number_format((float) $order->header_discount_total, 2) }}</dd>
                    </div>
                @endif

                @foreach ($order->charges as $charge)
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">
                            {{ $charge->name }}
                            <span class="text-xs">
                                ({{ $charge->value_type === \App\Enums\ChargeValueType::Percentage
                                    ? rtrim(rtrim(number_format((float) $charge->value, 2), '0'), '.') . '%'
                                    : $currency . ' ' . number_format((float) $charge->value, 2) }})
                            </span>
                        </dt>
                        <dd @class(['tabular', 'text-error' => $charge->type->sign() < 0])>
                            {{ $charge->type->sign() < 0 ? '−' : '+' }}{{ number_format((float) $charge->computed_amount, 2) }}
                        </dd>
                    </div>
                @endforeach

                <div class="flex items-baseline justify-between border-t-2 border-base-300 pt-2">
                    <dt class="font-semibold">Grand total</dt>
                    <dd class="tabular text-lg font-semibold">
                        {{ $currency }} {{ number_format((float) $order->grand_total, 2) }}
                    </dd>
                </div>
            </dl>
        </div>

        @if ($order->remarks)
            <div class="mt-4 rounded-box bg-base-200 p-3 text-sm">
                <p class="text-xs font-medium text-base-content/60">Remarks</p>
                <p class="mt-1">{{ $order->remarks }}</p>
            </div>
        @endif
    </x-card>

    {{-- Receipts raised against this order --}}
    <x-card title="Goods receipts"
            :subtitle="$order->goodsReceipts->isEmpty() ? null : $order->goodsReceipts->count() . ' raised'">
        @if ($order->goodsReceipts->isEmpty())
            <p class="text-sm text-base-content/60">
                Nothing has been received yet.
                @if ($actions['receive'])
                    <a href="{{ route('purchase-orders.goods-receipts.create', $order) }}" class="link" wire:navigate>
                        Raise a receipt
                    </a>.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th class="text-right">Quantity</th>
                            <th>Received by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->goodsReceipts as $receipt)
                            <tr>
                                <td>
                                    <a href="{{ route('goods-receipts.show', $receipt) }}"
                                       class="link-hover link font-mono" wire:navigate>
                                        {{ $receipt->code }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-sm {{ $receiptBadge[$receipt->status->value] ?? 'badge-ghost' }}">
                                        {{ $receipt->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $receipt->location?->name }}</td>
                                <td>{{ $receipt->gr_date?->format('M d, Y') }}</td>
                                <td class="tabular text-right">
                                    {{ number_format((float) $receipt->items()->sum('qty_to_receive'), 2) }}
                                </td>
                                <td>{{ $receipt->user?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    {{-- Audit trail --}}
    <x-card title="History" :subtitle="$order->logs->count() . ' entries'">
        <ol class="space-y-2">
            @foreach ($order->logs as $log)
                <li class="flex flex-wrap items-baseline gap-2 border-b border-base-300 pb-2 text-sm last:border-0">
                    <span class="badge badge-ghost badge-sm">{{ $log->action->label() }}</span>

                    @if ($log->from_status || $log->to_status)
                        <span class="text-base-content/60">
                            {{ str($log->from_status ?: '—')->headline() }}
                            <x-icon name="arrow-right" class="mx-0.5 inline size-3 opacity-40" />
                            {{ str($log->to_status ?: '—')->headline() }}
                        </span>
                    @endif

                    <span>{{ $log->remarks }}</span>

                    <span class="ml-auto text-xs text-base-content/50">
                        {{ $log->user?->name ?? 'System' }} · {{ $log->created_at?->format('M d, Y g:i A') }}
                    </span>
                </li>
            @endforeach
        </ol>
    </x-card>

    {{-- One confirmation dialog, wording chosen per action --}}
    <x-modal name="confirm-action" :title="$prompts[$confirming][0] ?? 'Confirm'">
        <p class="text-sm text-base-content/70">
            {{ $prompts[$confirming][1] ?? '' }}
        </p>

        <x-slot:actions>
            <button type="button" class="btn btn-ghost btn-sm" x-on:click="open = false">Cancel</button>

            @if ($confirming !== '')
                <button type="button"
                        class="btn btn-sm {{ $prompts[$confirming][3] ?? 'btn-primary' }}"
                        wire:click="{{ $confirming }}"
                        wire:loading.attr="disabled">
                    <span class="loading loading-spinner loading-xs" wire:loading wire:target="{{ $confirming }}"></span>
                    {{ $prompts[$confirming][2] ?? 'Confirm' }}
                </button>
            @endif
        </x-slot:actions>
    </x-modal>
</div>
