@extends('layouts.app')

@section('content')
    <div>
        <div class="card">
            <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="space-y-2">
                    <x-input-label>Services</x-input-label>
                    <form method="GET" action="{{ route('horizon.queues.index') }}" data-turbo-frame="_top">
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
                body-id="turbo-tbody-horizon-queue-list"
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job_count">Pending Jobs</th>
                    </tr>
                </x-slot:head>
                @include('horizon.queues.partials.queue-tbody', ['queues' => $queues])
            </x-table>
        </div>
    </div>
@endsection
