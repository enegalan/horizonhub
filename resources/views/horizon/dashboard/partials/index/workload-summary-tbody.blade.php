@php
    /** @var list<array<string, mixed>> $workloadRows */
    $workloadRows ??= [];
@endphp
<tr id="dashboard-workload-empty" style="{{ \count($workloadRows) > 0 ? 'display:none;' : '' }}">
    <td colspan="5" data-column-id="service">
        <x-empty-state
            title="No queue workload"
            description="Queues will show here once work is pending across your services."
        >
            <x-slot name="icon">
                <x-icons.queue-list class="empty-state-icon" />
            </x-slot>
        </x-empty-state>
    </td>
</tr>
@foreach($workloadRows as $row)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="wl-top-{{ (int) ($row['service_id'] ?? 0) }}-{{ rawurlencode((string) ($row['queue'] ?? '')) }}">
        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="service">
            @if(! empty($row['service_id']))
                <a href="{{ route('horizon.services.show', ['service' => $row['service_id']]) }}" class="link" data-turbo-action="replace">{{ $row['service'] ?? '' }}</a>
            @else
                {{ $row['service'] ?? '' }}
            @endif
        </td>
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">{{ $row['queue'] ?? '' }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ isset($row['jobs']) ? (int) $row['jobs'] : 0 }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
            @if(! empty($row['processes']))
                {{ (int) $row['processes'] }}
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
            @if(! empty($row['wait']))
                @php($waitSeconds = (float) $row['wait'])
                <span data-wait-seconds="{{ $waitSeconds }}">
                    {{ number_format($waitSeconds, 2, '.', '') }} s
                </span>
            @else
                –
            @endif
        </td>
    </tr>
@endforeach
