@php
    /** @var array|null $supervisorsRows */
@endphp
<tr id="metrics-supervisors-empty" style="{{ (is_array($supervisorsRows ?? null) && \count($supervisorsRows) > 0) ? 'display:none;' : '' }}">
    <td colspan="5" data-column-id="service">
        <div class="empty-state">
            <x-heroicon-o-queue-list class="empty-state-icon" />
            <p class="empty-state-title">No supervisor data yet</p>
            <p class="empty-state-description">
                Supervisors will appear here once Horizon is running on your services and the Hub agent has synced data.
            </p>
        </div>
    </td>
</tr>
@foreach($supervisorsRows ?? [] as $row)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="sup-{{ (int) ($row['service_id'] ?? 0) }}-{{ rawurlencode((string) ($row['name'] ?? '')) }}">
        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="service">
            @if(! empty($row['service_id']))
                <a href="{{ route('horizon.services.show', ['service' => $row['service_id']]) }}" class="link" data-turbo-action="replace">{{ $row['service'] ?? '' }}</a>
            @else
                {{ $row['service'] ?? '' }}
            @endif
        </td>
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="supervisor">{{ $row['name'] ?? '' }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground text-right" data-column-id="jobs">
            {{ isset($row['jobs']) ? (int) $row['jobs'] : 0 }}
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground text-right" data-column-id="processes">
            @if(isset($row['processes']) && $row['processes'] !== null)
                {{ (int) $row['processes'] }}
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 text-xs" data-column-id="status">
            @php($status = $row['status'] ?? 'stale')
            <span class="{{ $status === 'online' ? 'badge-success' : 'badge-warning' }}">
                {{ $status === 'online' ? 'Online' : 'Stale' }}
            </span>
        </td>
    </tr>
@endforeach
