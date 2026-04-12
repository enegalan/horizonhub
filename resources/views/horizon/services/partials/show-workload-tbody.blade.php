@forelse($workloadQueues as $row)
    <tr class="transition-colors hover:bg-muted/30">
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
            {{ $row->queue }}
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">
            {{ number_format($row->jobs) }}
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
            {{ $row->processes !== null ? number_format($row->processes) : '–' }}
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
            @if($row->wait !== null)
                <span data-wait-seconds="{{ $row->wait }}">{{ number_format($row->wait, 2) }} s</span>
            @else
                –
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4" data-column-id="queue">
            <div class="empty-state">
                <x-heroicon-o-queue-list class="empty-state-icon" />
                <p class="empty-state-title">No queues for this service yet</p>
                <p class="empty-state-description">Queues will appear here once jobs are dispatched to this service.</p>
            </div>
        </td>
    </tr>
@endforelse
