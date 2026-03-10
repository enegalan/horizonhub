<tbody class="divide-y divide-border" data-table-body="horizon-queue-list">
    @forelse($queues as $row)
        @php $state = $queueStates->get($row->service_id . '|' . $row->queue); @endphp
        <tr class="transition-colors hover:bg-muted/30">
            <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">
                @if($row->service)
                    <a href="{{ route('horizon.services.show', $row->service) }}" wire:navigate class="link">{{ $row->service->name }}</a>
                @else
                    –
                @endif
            </td>
            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">{{ $row->queue }}</td>
            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job_count">{{ number_format($row->job_count) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="3" data-column-id="service">
                <div class="empty-state">
                    <x-heroicon-o-queue-list class="empty-state-icon" />
                    <p class="empty-state-title">No queues yet</p>
                    <p class="empty-state-description">Queues will appear once services are registered and jobs are dispatched.</p>
                </div>
            </td>
        </tr>
    @endforelse
</tbody>
