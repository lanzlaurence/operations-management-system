@php
    $receipt = $goodsReceipt;
    $actions = $this->actions();
    $summary = $this->summary();
    $movements = $this->movements();

    $statusBadge = [
        'pending' => 'badge-warning',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error',
    ];

    $prompts = [
        'complete' => [
            'Complete this goods receipt?',
            'The quantities below are booked into the location, the order lines are updated, and each material\'s average cost is recalculated. This is the point stock exists.',
            'Complete receipt',
            'btn-primary',
        ],
        'cancel' => [
            'Cancel this goods receipt?',
            'If it was completed, the stock it booked in is reversed out. That is refused when the stock has since been issued — cancel the goods issue first.',
            'Cancel receipt',
            'btn-error',
        ],
        'revert' => [
            'Revert this receipt to pending?',
            'It becomes editable again and its quantities are held against the order once more. No stock moves.',
            'Revert to pending',
            'btn-warning',
        ],
        'delete' => [
            'Delete this goods receipt?',
            'The pending receipt is removed and the quantities it held are released back to the order.',
            'Delete receipt',
            'btn-error',
        ],
    ];
@endphp

<div class="space-y-4">
    <x-page-header :title="$receipt->code"
                   :subtitle="$receipt->purchaseOrder?->code . ' · ' . $receipt->purchaseOrder?->vendor?->name">
        <x-slot:actions>
            <span class="badge {{ $statusBadge[$receipt->status->value] ?? 'badge-ghost' }}">
                {{ $receipt->status->label() }}
            </span>

            @if ($actions['complete'])
                @can('goods-receipt-complete')
                    <button type="button" class="btn btn-primary btn-sm" wire:click="confirm('complete')">
                        <x-icon name="check-circle" class="size-4" />
                        Complete
                    </button>
                @endcan
            @endif

            @if ($actions['edit'])
                @can('goods-receipt-edit')
                    <a href="{{ route('goods-receipts.edit', $receipt) }}" class="btn btn-ghost btn-sm" wire:navigate>
                        <x-icon name="pencil-square" class="size-4" />
                        Edit
                    </a>
                @endcan
            @endif

            @if ($actions['revert'] || $actions['cancel'] || $actions['delete'])
                <div class="dropdown dropdown-end">
                    <button type="button" class="btn btn-ghost btn-sm">
                        <x-icon name="ellipsis-vertical" class="size-4" />
                    </button>

                    <ul class="menu dropdown-content z-50 w-52 rounded-box border border-base-300 bg-base-100 p-1 shadow-lg">
                        @if ($actions['revert'])
                            @can('goods-receipt-revert')
                                <li>
                                    <button type="button" wire:click="confirm('revert')">
                                        <x-icon name="arrow-uturn-left" class="size-4" />
                                        Revert to pending
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['cancel'])
                            @can('goods-receipt-cancel')
                                <li>
                                    <button type="button" class="text-error" wire:click="confirm('cancel')">
                                        <x-icon name="x-circle" class="size-4" />
                                        Cancel receipt
                                    </button>
                                </li>
                            @endcan
                        @endif

                        @if ($actions['delete'])
                            @can('goods-receipt-delete')
                                <li>
                                    <button type="button" class="text-error" wire:click="confirm('delete')">
                                        <x-icon name="trash" class="size-4" />
                                        Delete receipt
                                    </button>
                                </li>
                            @endcan
                        @endif
                    </ul>
                </div>
            @endif

            <a href="{{ route('goods-receipts.index') }}" class="btn btn-ghost btn-sm" wire:navigate>
                <x-icon name="arrow-left" class="size-4" />
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($receipt->status->isPending())
        <div class="alert alert-warning alert-soft">
            <x-icon name="clock" class="size-5" />
            <span>
                This receipt is prepared but not completed, so no stock has moved yet. Its quantities are held
                against the order in the meantime.
            </span>
        </div>
    @endif

    {{-- Header facts --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Purchase order</p>
            <a href="{{ route('purchase-orders.show', $receipt->purchase_order_id) }}"
               class="link-hover link font-mono font-medium" wire:navigate>
                {{ $receipt->purchaseOrder?->code }}
            </a>
            <p class="text-xs text-base-content/50">{{ $receipt->purchaseOrder?->vendor?->name }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Location</p>
            <p class="font-medium">{{ $receipt->location?->name }}</p>
            <p class="font-mono text-xs text-base-content/50">{{ $receipt->location?->code }}</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Quantity</p>
            <p class="tabular text-xl font-semibold">{{ number_format($summary['quantity'], 2) }}</p>
            <p class="text-xs text-base-content/50">over {{ $summary['lines'] }} line(s)</p>
        </div>

        <div class="rounded-box border border-base-300 bg-base-100 p-4">
            <p class="text-sm text-base-content/60">Value</p>
            <p class="tabular text-xl font-semibold">{{ $currency }} {{ number_format($summary['value'], 2) }}</p>
            <p class="text-xs text-base-content/50">
                {{ $receipt->gr_date?->format('M d, Y') }} · {{ $receipt->user?->name }}
            </p>
        </div>
    </div>

    {{-- Lines --}}
    <x-card title="Items">
        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th class="text-right">Ordered</th>
                        <th class="text-right">Received before</th>
                        <th class="text-right">This receipt</th>
                        <th class="text-right">Unit cost</th>
                        <th class="text-right">Value</th>
                        <th>Serial / batch</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipt->items as $item)
                        <tr>
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
                            <td class="tabular text-right text-base-content/60">
                                {{ number_format((float) $item->qty_received, 2) }}
                            </td>
                            <td class="tabular text-right font-medium text-success">
                                +{{ number_format((float) $item->qty_to_receive, 2) }}
                            </td>
                            <td class="tabular text-right">{{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="tabular text-right font-medium">{{ number_format($item->lineValue(), 2) }}</td>
                            <td class="text-xs">
                                @if ($item->serial_number)
                                    <div><span class="text-base-content/50">SN</span> {{ $item->serial_number }}</div>
                                @endif
                                @if ($item->batch_number)
                                    <div><span class="text-base-content/50">Batch</span> {{ $item->batch_number }}</div>
                                @endif
                                @if (! $item->serial_number && ! $item->batch_number)
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($receipt->remarks)
            <div class="mt-4 rounded-box bg-base-200 p-3 text-sm">
                <p class="text-xs font-medium text-base-content/60">Remarks</p>
                <p class="mt-1">{{ $receipt->remarks }}</p>
            </div>
        @endif
    </x-card>

    {{-- The movements this receipt produced --}}
    @if ($movements->isNotEmpty())
        <x-card title="Stock movements" subtitle="What this receipt did to inventory">
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>Movement</th>
                            <th>Type</th>
                            <th>Material</th>
                            <th class="text-right">Before</th>
                            <th class="text-right">Change</th>
                            <th class="text-right">After</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movements as $movement)
                            @php $change = (float) $movement->quantity_change; @endphp

                            <tr>
                                <td class="font-mono text-sm">{{ $movement->movement_code }}</td>
                                <td>
                                    <span @class([
                                        'badge badge-sm',
                                        'badge-success' => $change > 0,
                                        'badge-error' => $change < 0,
                                    ])>
                                        {{ $movement->type->label() }}
                                    </span>
                                </td>
                                <td>
                                    {{ $movement->material?->name }}
                                    <div class="font-mono text-xs text-base-content/50">
                                        {{ $movement->inventory?->code }}
                                    </div>
                                </td>
                                <td class="tabular text-right text-base-content/60">
                                    {{ number_format((float) $movement->quantity_before, 2) }}
                                </td>
                                <td class="tabular text-right font-medium {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                                    {{ $change > 0 ? '+' : '' }}{{ number_format($change, 2) }}
                                </td>
                                <td class="tabular text-right font-medium">
                                    {{ number_format((float) $movement->quantity_after, 2) }}
                                </td>
                                <td class="text-sm">{{ $movement->created_at?->format('M d, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- Audit trail --}}
    <x-card title="History" :subtitle="$receipt->logs->count() . ' entries'">
        <ol class="space-y-2">
            @foreach ($receipt->logs as $log)
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

    <x-modal name="confirm-action" :title="$prompts[$confirming][0] ?? 'Confirm'">
        <p class="text-sm text-base-content/70">{{ $prompts[$confirming][1] ?? '' }}</p>

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
