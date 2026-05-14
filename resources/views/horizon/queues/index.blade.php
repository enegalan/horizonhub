@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div class="relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card px-5 py-5 sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Workload</p>
                        <h2 class="text-section-title text-foreground">Queues</h2>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Pending jobs per queue across your Horizon services. Filter by service to focus on a subset of workers.
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="space-y-2">
                        <x-input-label id="queues-index-services-label" for="queues-index-services">Services</x-input-label>
                        <form method="GET" action="{{ route('horizon.queues.index') }}" data-turbo-frame="_top">
                            <x-multiselect
                                id="queues-index-services"
                                labelled-by="queues-index-services-label"
                                name="queue_services"
                                class="w-full min-w-0 sm:w-56"
                                :submit-on-change="true"
                                :selected="$serviceIds ?? []"
                                placeholder="All services"
                                empty-message="No services found"
                            >
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </x-multiselect>
                        </form>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-2 sm:px-6">
                <div
                    id="turbo-horizon-queue-stats"
                    class="contents"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        @for ($i = 0; $i < 2; $i++)
                            <div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
                                <div class="skeleton h-3 w-16" style="--skeleton-delay: {{ $i * 80 }}ms"></div>
                                <div class="skeleton mt-3 h-8 w-14" style="--skeleton-delay: {{ ($i * 80) + 100 }}ms"></div>
                            </div>
                        @endfor
                    @else
                        @include('horizon.queues.partials.queue-page-stats-inner', [
                            'queueCount' => $queueCount ?? 0,
                            'totalJobs' => $totalJobs ?? 0,
                        ])
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                <h3 class="text-section-title text-foreground">By queue</h3>
                <a href="{{ route('horizon.metrics') }}" class="link text-xs" data-turbo-action="replace">Metrics</a>
            </div>

            <x-table
                resizable-key="horizon-queue-list"
                column-ids="service,queue,job_count"
                body-key="horizon-queue-list"
                body-id="turbo-tbody-horizon-queue-list"
                stream-patch-children
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header min-w-[120px] px-4 py-2.5" data-column-id="service">Service</th>
                        <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job_count">Pending jobs</th>
                    </tr>
                </x-slot:head>
                @include('horizon.queues.partials.queue-tbody', [
                    'queues' => $queues,
                    'defer' => $defer ?? false,
                ])
            </x-table>
        </div>
    </div>
@endsection
