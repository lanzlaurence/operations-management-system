@php
    $order = $salesOrder;
    $actions = $this->actions();
    $outstanding = $this->outstanding();
    $quantities = $this->quantities();
    $percent = $this->shippedPercent();

    $statusBadge = [
        'draft' => 'badge-ghost',
        'posted' => 'badge-info',
        'partially_shipped' => 'badge-warning',
        'fully_shipped' => 'badge-success',
        'cancelled' => 'badge-error',
    ];

    $issueBadge = [
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error',
    ];

    // What each confirmation should say before it is committed.
    $prompts = [
        'post' => [
            'Post this sales order?',
            'Posting releases the order to the customer and allows goods issues to be raised against it. The lines can no longer be edited.',
            'Post order',
            'btn-primary',
        ],
        'cancel' => [
            'Cancel this sales order?',
            'Every goods issue raised against it is cancelled too, and any stock they booked in is reversed out. This is refused if that stock has since been issued.',
            'Cancel order',
            'btn-error',
        ],
        'revert' => [
            'Revert this order to draft?',
            'The order becomes editable again. This is only allowed while no issue has booked stock; cancelled issues are reopened as pending.',
            'Revert to draft',
            'btn-warning',
        ],
        'delete' => [
            'Delete this sales order?',
            'The draft and any issues prepared against it are removed. Nothing that shipped stock can be deleted.',
            'Delete order',
            'btn-error',
        ],
    ];
@endphp

<div class="space-y-4">
    <x-page-header :title="$order->code"
                   :subtitle="$order->customer?->name . ($order->reference_no ? ' · ' . $order->reference_no : '')">
        <x-slot:actions>
            <span class="badge {{ $statusBadge[$order->status->value] ?? 'badge-ghost' }}">
                {{ $order->status->label() }}
            </span>

            @if ($actions['ship'])
                @can('goods-issue-create')
                    <a href="{{ route('sales-orders.goods-issues.create', $order) }}"
                       class="btn btn-primary btn-sm" wire:navigate>
                        <x-icon name="archive-box-arrow-down" class="size-4" />
                        Ship
                    </a>
                @endcan
            @endif

            @if ($actions['post'])
                @can('sales-order-post')
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirm('post')">
                        <x-icon name="paper-airplane" class="size-4" />
                        Post
                    </button>
                @endcan
            @endif

            @if ($actions['edit'])
                @can('sales-order-edit')
                    <a href="{{ route('sales-orders.edit', $order) }}" class="btn btn-ghost btn-sm" wire:navigate>
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
                            @can('sales-order-revert')
                                <li>
                                    <button type="button" wire:click="confirm('revert')">
                                        <x-icon name="arrow-uturn-left" class="size-4" />
                                        Revert to draft
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['cancel'])
                            @can('sales-order-cancel')
                                <li>
                                    <button type="button" class="text-error" wire:click="confirm('cancel')">
                                        <x-icon name="x-circle" class="size-4" />
                                        Cancel order
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['delete'])
                            @can('sales-order-delete')
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

            <a href="{{ route('sales-orders.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Header facts --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Customer</p>
            <a href="{{ route('customers.show', $order->customer_id) }}" class="link-hover link font-medium" wire:navigate>
                {{ $order->customer?->name }}
            </a>
            <p class="text-xs text-base-content/50">
                {{ $order->customer?->code }} · {{ $order->customer?->payment_terms ?: 'terms not set' }}
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
            <p class="text-sm text-base-content/60">Shipped</p>
            <p class="tabular font-medium">
                {{ number_format($quantities['shipped'], 2) }} of {{ number_format($quantities['ordered'], 2) }}
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
                        <th class="text-right">Shipped</th>
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
                            <td class="tabular text-right">{{ number_format((float) $item->qty_shipped, 2) }}</td>
                            <td class="tabular text-right">
                                @php $left = $outstanding[$item->id] ?? 0; @endphp
                                <span @class(['text-base-content/40' => $left <= 0])>
                                    {{ number_format($left, 2) }}
                                </span>
                            </td>
                            <td class="tabular text-right">
                                {{ number_format((float) $item->unit_price_after_discount, 2) }}
                                @if ((float) $item->discount_amount > 0)
                                    <div class="text-xs text-base-content/50">
                                        from {{ number_format((float) $item->unit_price, 2) }}
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

    {{-- Issues raised against this order --}}
    <x-card title="Goods issues"
            :subtitle="$order->goodsIssues->isEmpty() ? null : $order->goodsIssues->count() . ' raised'">
        @if ($order->goodsIssues->isEmpty())
            <p class="text-sm text-base-content/60">
                Nothing has been shipped yet.
                @if ($actions['ship'])
                    <a href="{{ route('sales-orders.goods-issues.create', $order) }}" class="link" wire:navigate>
                        Raise a issue
                    </a>.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th class="text-right">Quantity</th>
                            <th>Shipped by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->goodsIssues as $issue)
                            <tr>
                                <td>
                                    <a href="{{ route('goods-issues.show', $issue) }}"
                                       class="link-hover link font-mono" wire:navigate>
                                        {{ $issue->code }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-sm {{ $issueBadge[$issue->status->value] ?? 'badge-ghost' }}">
                                        {{ $issue->status->label() }}
                                    </span>
                                </td>
                                <td>{{ $issue->location?->name }}</td>
                                <td>{{ $issue->gi_date?->format('M d, Y') }}</td>
                                <td class="tabular text-right">
                                    {{ number_format((float) $issue->items()->sum('qty_to_ship'), 2) }}
                                </td>
                                <td>{{ $issue->user?->name }}</td>
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
