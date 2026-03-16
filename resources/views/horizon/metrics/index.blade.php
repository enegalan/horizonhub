@php($horizonStreamMode = 'metrics')
@php($metricsBaseUrls = [
    'summary' => route('horizon.metrics.data.summary'),
    'avgRuntime' => route('horizon.metrics.data.avg-runtime'),
    'failureRate' => route('horizon.metrics.data.failure-rate-over-time'),
    'supervisors' => route('horizon.metrics.data.supervisors'),
    'workload' => route('horizon.metrics.data.workload'),
])
@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonMetricsPage ? window.horizonMetricsPage({ baseUrls: {{ Js::from($metricsBaseUrls) }}, initialServiceId: {{ Js::from(isset($serviceFilter) && $serviceFilter !== '' ? $serviceFilter : null) }} }) : {}"
        x-init="typeof init === 'function' ? init() : null"
    >
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label for="metrics-service-filter" class="label-muted text-sm">Filter by service</label>
            <x-select id="metrics-service-filter" class="w-48" placeholder="All services">
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected(isset($serviceFilter) && $serviceFilter !== '' && (int) $serviceFilter === (int) $service->id)>{{ $service->name }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <h3 class="label-muted">Jobs past minute</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-jobs-minute"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-jobs-minute" class="text-2xl font-semibold text-foreground">—</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Jobs past hour</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-jobs-hour"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-jobs-hour" class="text-2xl font-semibold text-foreground">—</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Failed jobs (past 7 days)</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-failed-seven"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-failed-seven" class="text-2xl font-semibold text-foreground">—</span>
                </div>
            </div>
        </div>

        <div class="grid gap-4">
            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-1">Failure rate (last 24h)</h3>
                <div class="flex items-center gap-2 min-h-[2rem]">
                    <span id="metrics-loader-failure-rate"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <p id="metrics-value-failure-rate" class="text-xl font-semibold text-foreground">—</p>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Failure rate over time (last 24h, %)</h3>
                <div class="relative h-56">
                    <div id="metrics-loader-failure-rate-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                        <x-loader class="size-8 text-muted-foreground" />
                    </div>
                    <div id="failure-rate-chart" class="h-56"></div>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Average job runtime (last 24h, seconds)</h3>
                <div class="relative h-56">
                    <div id="metrics-loader-runtime-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded">
                        <x-loader class="size-8 text-muted-foreground" />
                    </div>
                    <div id="runtime-chart" class="h-56"></div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Current workload</h3>
                    <p id="metrics-workload-summary" class="text-xs text-muted-foreground"></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full" data-resizable-table="horizon-metrics-queues" data-column-ids="service,queue,jobs,processes,wait">
                        <thead>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                                <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="queue">Queue</th>
                                <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                                <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                                <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border" data-table-body="horizon-metrics-queues" id="metrics-workload-body">
                            <tr id="metrics-workload-empty">
                                <td colspan="5" data-column-id="service">
                                    <div class="empty-state">
                                        <x-heroicon-o-queue-list class="empty-state-icon" />
                                        <p class="empty-state-title">No queues yet</p>
                                        <p class="empty-state-description">Queues will appear here once jobs are dispatched to your services.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Supervisors</h3>
                    <p id="metrics-supervisors-summary" class="text-xs text-muted-foreground"></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full" data-resizable-table="horizon-metrics-supervisors" data-column-ids="service,supervisor,jobs,processes,status">
                        <thead>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                                <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                                <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="jobs">Jobs</th>
                                <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="processes">Processes</th>
                                <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="status">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border" data-table-body="horizon-metrics-supervisors" id="metrics-supervisors-body">
                            <tr id="metrics-supervisors-empty">
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
