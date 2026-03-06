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
            <td class="px-4 py-2.5" data-column-id="status">
                @if($state !== null && $state->is_paused)
                    <span class="badge-warning">Paused</span>
                @else
                    <span class="badge-success">Running</span>
                @endif
            </td>
            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job_count">{{ number_format($row->job_count) }}</td>
            <td class="px-4 py-2.5" data-column-id="actions">
                @if($row->service && $row->service->base_url)
                    <div class="flex items-center gap-2">
                        @php $isPaused = $state !== null && $state->is_paused; $toggleAction = $isPaused ? 'resume' : 'pause'; @endphp
                        <x-button variant="outline" type="button" data-queue-action="{{ $toggleAction }}" data-service-id="{{ $row->service_id }}" data-queue="{{ $row->queue }}" class="queue-action-btn h-8 min-h-8 p-2" aria-label="{{ $isPaused ? 'Resume' : 'Pause' }}" title="{{ $isPaused ? 'Resume' : 'Pause' }}">
                            <span class="queue-btn-icon">
                            @if($isPaused)
                                <x-heroicon-o-play class="size-4" />
                            @else
                                <x-heroicon-o-pause class="size-4" />
                            @endif
                            </span>
                            <span class="queue-btn-spinner hidden" aria-hidden="true">
                                <x-loader />
                            </span>
                        </x-button>
                    </div>
                @else
                    <span class="text-muted-foreground text-xs">No base URL</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="5" data-column-id="service">
                <div class="empty-state">
                    <x-heroicon-o-queue-list class="empty-state-icon" />
                    <p class="empty-state-title">No queues yet</p>
                    <p class="empty-state-description">Queues will appear once services are registered and jobs are dispatched.</p>
                </div>
            </td>
        </tr>
    @endforelse
</tbody>
