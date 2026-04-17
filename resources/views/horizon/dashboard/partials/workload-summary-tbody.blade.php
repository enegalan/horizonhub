@php
    /** @var list<array<string, mixed>> $workloadRows */
@endphp
<tr id="dashboard-workload-empty" style="{{ (\count($workloadRows ?? []) > 0) ? 'display:none;' : '' }}">
    <td colspan="5" data-column-id="service">
        <div class="empty-state">
            <x-heroicon-o-queue-list class="empty-state-icon" />
            <p class="empty-state-title">No queue workload</p>
            <p class="empty-state-description">Queues will show here once work is pending across your services.</p>
        </div>
    </td>
</tr>
@foreach($workloadRows ?? [] as $row)
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
            @if(isset($row['processes']) && $row['processes'] !== null)
                {{ (int) $row['processes'] }}
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
            @if(isset($row['wait']) && $row['wait'] !== null)
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
