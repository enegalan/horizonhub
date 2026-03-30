@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonQueueList ? window.horizonQueueList() : {}"
        x-init="typeof init === 'function' ? init() : null"
    >
        <div class="card">
            <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="space-y-2">
                    <x-input-label>Services</x-input-label>
                    <form method="GET" action="{{ route('horizon.queues.index') }}">
                        <x-multiselect
                            name="queue_services"
                            class="w-56"
                            :submit-on-change="true"
                            :selected="$serviceIds ?? []"
                            placeholder="All services"
                        >
                            @foreach($services as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </x-multiselect>
                    </form>
                </div>
                @if($queueCount > 0)
                    <p class="text-xs text-muted-foreground">{{ $queueCount }} queue(s), {{ number_format($totalJobs) }} total jobs</p>
                @endif
            </div>
            <x-table
                resizable-key="horizon-queue-list"
                column-ids="service,queue,job_count"
                body-key="horizon-queue-list"
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job_count">Pending Jobs</th>
                    </tr>
                </x-slot:head>
                @forelse($queues as $row)
                    <tr class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">
                            @if($row->service)
                                <a href="{{ route('horizon.services.show', $row->service) }}" class="link">{{ $row->service->name }}</a>
                            @else
                                –
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
                            {{ $row->queue }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job_count">
                            {{ number_format($row->job_count) }}
                        </td>
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
            </x-table>
        </div>
    </div>
@endsection
