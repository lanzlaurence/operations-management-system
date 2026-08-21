@php
    $breakdown = $this->actionBreakdown();

    $actionBadge = [
        'created' => 'badge-ghost',
        'updated' => 'badge-info',
        'deleted' => 'badge-error',
        'posted' => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-error',
        'reverted' => 'badge-warning',
        'gr_created' => 'badge-ghost',
        'gr_deleted' => 'badge-error',
        'gi_created' => 'badge-ghost',
        'gi_deleted' => 'badge-error',
        'status_recalculated' => 'badge-ghost',
    ];
@endphp

<div class="space-y-4">
    <x-page-header title="Transaction log"
                   subtitle="Every status change on purchase orders, sales orders, receipts and issues">
        <x-slot:actions>
            @can('activity-inventory-log')
                <a href="{{ route('activity.inventory-log') }}" class="btn btn-ghost btn-sm" wire:navigate>
                    <x-icon name="clipboard-document-list" class="size-4" />
                    Inventory log
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- What has been happening, under the current filters --}}
    @if ($breakdown !== [])
        <div class="flex flex-wrap gap-2">
            @foreach ($breakdown as $action => $total)
                <button type="button"
                        @class([
                            'badge badge-lg gap-2',
                            $actionBadge[$action] ?? 'badge-ghost',
                            'badge-outline' => $actionFilter !== $action,
                        ])
                        wire:click="$set('actionFilter', '{{ $actionFilter === $action ? '' : $action }}')">
                    {{ \App\Enums\DocumentAction::tryFrom($action)?->label() ?? $action }}
                    <span class="tabular font-semibold">{{ number_format($total) }}</span>
                </button>
            @endforeach
        </div>
    @endif

    <x-table :paginator="$records"
             :sort="$sortField"
             :direction="$sortDirection"
             search-placeholder="Search document code, reference, user or remarks…"
             empty-title="No entries"
             empty-message="Nothing matches the current filters.">

        <x-slot:toolbar>
            <select class="select select-bordered select-sm w-40" wire:model.live="documentFilter">
                <option value="">All documents</option>
                @foreach ($documentOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select class="select select-bordered select-sm w-44" wire:model.live="actionFilter">
                <option value="">All actions</option>
                @foreach ($actionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <input type="date" class="input input-bordered input-sm w-36" wire:model.live="fromDate" aria-label="From date">
            <input type="date" class="input input-bordered input-sm w-36" wire:model.live="toDate" aria-label="To date">

            <label class="label cursor-pointer gap-2 text-sm" title="Hide the derived status recalculation entries">
                <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="hideSystem">
                People only
            </label>

            @if ($this->hasFilters())
                <button type="button" class="btn btn-ghost btn-sm" wire:click="clearFilters">
                    <x-icon name="x-mark" class="size-4" />
                    Clear
                </button>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-table.heading field="created_at" width="160px">Date &amp; time</x-table.heading>
            <x-table.heading width="140px">User</x-table.heading>
            <x-table.heading width="130px">Document</x-table.heading>
            <x-table.heading width="130px">Code</x-table.heading>
            <x-table.heading field="action" width="150px">Action</x-table.heading>
            <x-table.heading width="200px">Status change</x-table.heading>
            <x-table.heading>Remarks</x-table.heading>
        </x-slot:head>

        @foreach ($records as $log)
            @php $url = $this->documentUrl($log); @endphp

            <tr wire:key="log-{{ $log->id }}">
                <td class="whitespace-nowrap text-sm">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                <td class="text-sm">
                    {{ $log->user?->name ?? 'System' }}
                    @if ($log->ip_address)
                        <div class="font-mono text-xs text-base-content/40">{{ $log->ip_address }}</div>
                    @endif
                </td>
                <td class="text-sm text-base-content/70">{{ $log->documentLabel() ?: '—' }}</td>
                <td>
                    @if ($url && $log->loggable)
                        <a href="{{ $url }}" class="link-hover link font-mono text-sm">{{ $log->loggable->code }}</a>
                    @else
                        <span class="font-mono text-sm text-base-content/40">
                            {{ $log->loggable?->code ?? 'deleted' }}
                        </span>
                    @endif
                </td>
                <td>
                    <span class="badge badge-sm {{ $actionBadge[$log->action->value] ?? 'badge-ghost' }}">
                        {{ $log->action->label() }}
                    </span>
                </td>
                <td class="text-sm">
                    @if ($log->from_status || $log->to_status)
                        <span class="text-base-content/60">{{ str($log->from_status ?: '—')->headline() }}</span>
                        <x-icon name="arrow-right" class="mx-1 inline size-3 opacity-40" />
                        <span class="font-medium">{{ str($log->to_status ?: '—')->headline() }}</span>
                    @else
                        <span class="text-base-content/40">—</span>
                    @endif
                </td>
                <td class="text-sm text-base-content/70">{{ $log->remarks ?: '—' }}</td>
            </tr>
        @endforeach
    </x-table>
</div>
