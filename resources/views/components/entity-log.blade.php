@props([
    'logs' => [],
    'title' => 'Change log',
])

{{--
    Change history for a master-data record.

    Feeds off the `logs` relation written by the HasEntityLogs concern, where an
    update carries a field-level diff. Shared by customers, vendors and
    materials so the audit trail reads the same everywhere.
--}}
@php
    $logs = collect($logs);

    $badge = [
        'created' => 'badge-success',
        'updated' => 'badge-info',
        'deleted' => 'badge-error',
        'restored' => 'badge-warning',
    ];
@endphp

<x-card :title="$title" :subtitle="$logs->isEmpty() ? null : $logs->count() . ' entr' . ($logs->count() === 1 ? 'y' : 'ies')">
    @if ($logs->isEmpty())
        <p class="text-sm text-base-content/60">No changes recorded yet.</p>
    @else
        <ol class="space-y-3">
            @foreach ($logs as $log)
                @php $action = $log->action?->value ?? (string) $log->action; @endphp

                <li class="rounded-box border border-base-300 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm {{ $badge[$action] ?? 'badge-ghost' }}">
                            {{ ucfirst($action) }}
                        </span>

                        <span class="text-sm font-medium">{{ $log->user?->name ?? 'System' }}</span>

                        <span class="text-sm text-base-content/60" title="{{ $log->created_at }}">
                            {{ $log->created_at?->format('M d, Y g:i A') }}
                        </span>
                    </div>

                    @if ($log->remarks)
                        <p class="mt-2 text-sm text-base-content/70">{{ $log->remarks }}</p>
                    @endif

                    @if (! empty($log->changes))
                        <div class="mt-2 overflow-x-auto">
                            <table class="table table-xs">
                                <thead>
                                    <tr>
                                        <th class="w-40">Field</th>
                                        <th>From</th>
                                        <th>To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($log->changes as $change)
                                        <tr>
                                            <td class="font-medium">
                                                {{ str($change['field'] ?? '')->headline() }}
                                            </td>
                                            <td class="text-base-content/60">
                                                {{ ($change['old'] ?? '') === '' ? '—' : $change['old'] }}
                                            </td>
                                            <td>{{ ($change['new'] ?? '') === '' ? '—' : $change['new'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
