@php
    /** @var \Illuminate\Support\Collection $queues */
@endphp
@if(!empty($defer) && $queues->isEmpty())
    <x-skeleton.table-rows rows="8" columns="3" />
@else
@forelse($queues as $row)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="q-{{ (int) ($row->service?->id ?? 0) }}-{{ rawurlencode((string) $row->queue) }}">
        <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">
            @if($row->service)
                <a href="{{ route('horizon.services.show', $row->service) }}" class="link" data-turbo-action="replace">{{ $row->service->name }}</a>
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
            {{ $row->queue }}
        </td>
        <td class="px-4 py-2.5 text-sm font-medium tabular-nums text-foreground" data-column-id="job_count">
            {{ number_format($row->job_count) }}
        </td>
    </tr>
@empty
    <tr>
        <td colspan="3" data-column-id="service">
            <x-empty-state
                title="No queues yet"
                description="Queues will appear once services are registered and jobs are dispatched."
            >
                <x-slot name="icon">
                    <x-heroicon-o-queue-list class="empty-state-icon" />
                </x-slot>
            </x-empty-state>
        </td>
    </tr>
@endforelse
@endif
