@php
    /** @var list<array<string, mixed>> $workloadRows */
    $workloadRows ??= [];
    $includeServiceColumn ??= true;
    $emptyId ??= 'workload-empty';
    $rowIdPrefix ??= 'wl';
    $emptyTitle ??= 'No queue workload';
    $emptyDescription ??= 'Queues will show here once work is pending across your services.';
    $colspan = $includeServiceColumn ? 5 : 4;
    $emptyColumnId = $includeServiceColumn ? 'service' : 'queue';
@endphp
<tr id="{{ $emptyId }}" style="{{ \count($workloadRows) > 0 ? 'display:none;' : '' }}">
    <td colspan="{{ $colspan }}" data-column-id="{{ $emptyColumnId }}">
        <x-empty-state
            :title="$emptyTitle"
            :description="$emptyDescription"
        >
            <x-slot name="icon">
                <x-icons.queue-list class="empty-state-icon" />
            </x-slot>
        </x-empty-state>
    </td>
</tr>
@foreach($workloadRows as $row)
    @php
        $queue = (string) (is_object($row) ? ($row->queue ?? '') : ($row['queue'] ?? ''));
        $jobs = (int) (is_object($row) ? ($row->jobs ?? 0) : ($row['jobs'] ?? 0));
        $processes = is_object($row) ? ($row->processes ?? null) : ($row['processes'] ?? null);
        $wait = is_object($row) ? ($row->wait ?? null) : ($row['wait'] ?? null);
        $serviceId = (int) (is_object($row) ? ($row->service_id ?? 0) : ($row['service_id'] ?? 0));
        $serviceName = is_object($row) ? ($row->service ?? '') : ($row['service'] ?? '');
        $rowKey = $includeServiceColumn
            ? $rowIdPrefix . '-' . $serviceId . '-' . rawurlencode($queue)
            : $rowIdPrefix . '-' . rawurlencode($queue);
    @endphp
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="{{ $rowKey }}">
        @if($includeServiceColumn)
            <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="service">
                @if($serviceId > 0)
                    <a href="{{ route('horizon.services.show', ['service' => $serviceId]) }}" class="link" data-turbo-action="replace">{{ $serviceName }}</a>
                @else
                    {{ $serviceName }}
                @endif
            </td>
        @endif
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">{{ $queue }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ $jobs }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
            @if($processes !== null && $processes !== '')
                {{ (int) $processes }}
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
            @if($wait !== null && $wait !== '')
                @php($waitSeconds = (float) $wait)
                <span data-wait-seconds="{{ $waitSeconds }}">
                    {{ \App\Support\Jobs\JobRuntime::getFormattedRuntime($waitSeconds) }}
                </span>
            @else
                –
            @endif
        </td>
    </tr>
@endforeach
