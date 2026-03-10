@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-2">
                <x-input-label>Service</x-input-label>
                <form method="GET" action="{{ route('horizon.queues.index') }}">
                    <x-select name="service" class="w-48" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach($services as $s)
                            <option value="{{ $s->id }}" @selected($serviceFilter !== '' && (int) $serviceFilter === (int) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </x-select>
                </form>
            </div>
            @if($queueCount > 0)
                <p class="text-xs text-muted-foreground">{{ $queueCount }} queue(s), {{ number_format($totalJobs) }} total jobs</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-queue-list" data-column-ids="service,queue,job_count">
                <thead>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job_count">Job count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border" data-table-body="horizon-queue-list">
                    @forelse($queues as $row)
                        @php $state = $queueStates->get($row->service_id . '|' . $row->queue); @endphp
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
                </tbody>
            </table>
        </div>
    </div>
@endsection

