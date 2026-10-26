@php
    /** @var \Illuminate\Support\Collection $queues */
@endphp
@if(!empty($defer) && $queues->isEmpty())
    <x-skeleton.table-rows rows="8" columns="7" />
@else
    @forelse($queues as $row)
        <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="q-{{ (int) ($row->service?->id ?? 0) }}-{{ rawurlencode((string) $row->queue) }}">
            <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate" data-column-id="service">
                @if($row->service)
                    <a href="{{ route('horizon.services.show', $row->service) }}" class="link" data-turbo-action="replace" title="{{ $row->service->name }}">{{ $row->service->name }}</a>
                @else
                    —
                @endif
            </td>
            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
                <a href="{{ route('horizon.queues.show', ['queue' => $row->queue]) }}" class="link" data-turbo-action="replace">{{ $row->queue }}</a>
            </td>
            <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="connection">
                {{ $row->connection !== '' ? $row->connection : '—' }}
            </td>
            <td class="px-4 py-2.5 text-sm font-medium tabular-nums text-foreground" data-column-id="job_count">
                {{ number_format($row->job_count) }}
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="processes">
                {{ $row->processes !== null ? number_format((int) $row->processes) : '—' }}
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="wait">
                {{ $row->wait !== null ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $row->wait) : '—' }}
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="throughput">
                {{ $row->throughput !== null ? number_format((float) $row->throughput, 2) : '—' }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                No queues reported by the selected services.
            </td>
        </tr>
    @endforelse
@endif
