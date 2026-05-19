@extends('layouts.app')

@section('content')
    <div
        id="horizon-metrics-dashboard"
        x-data="window.horizonMetricsPage ? window.horizonMetricsPage() : {}"
        x-init="typeof init === 'function' ? init() : null"
    >
        <script type="application/json" id="metrics-chart-data">@json($metricsChartData ?? [])</script>

        <div class="card mb-6 overflow-hidden">
            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <form method="GET" action="{{ route('horizon.metrics') }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="_top" data-service-tag-filter="1">
                    <x-service-tag-filter
                        :all-tags="$allTags ?? []"
                        :selected-tags="$selectedTags ?? []"
                        :show-service-multiselect="true"
                        :services="$services ?? collect()"
                        :service-ids="$selectedServiceIds ?? []"
                        service-multiselect-id="metrics-service-filter"
                        service-multiselect-name="service_id"
                    />
                    <x-button type="submit" class="h-9 shrink-0 text-sm">
                        Filter
                    </x-button>
                </form>
            </div>
        </div>

        <x-kpi-grid class="mb-6">
            <x-stat-card label="Jobs past minute" tone="emerald" value-id="metrics-value-jobs-minute">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $jobsPastMinute ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Jobs past hour" tone="sky" value-id="metrics-value-jobs-hour">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $jobsPastHour ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Failed jobs (past 7 days)" tone="rose" value-id="metrics-value-failed-seven">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $failedPastSevenDays ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Failure rate (last 24h)" tone="amber" value-id="metrics-value-failure-rate">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-24" />
                @else
                    @include('horizon.metrics.partials.failure-rate-value', ['failureRate24h' => $failureRate24h ?? null])
                @endif
            </x-stat-card>
        </x-kpi-grid>

        <div class="grid gap-4">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="card min-w-0 overflow-hidden p-4">
                    <h3 class="text-section-title text-foreground mb-2">Jobs per hour (last 24 hours)</h3>
                    <div class="chart-panel">
                        <div id="metrics-loader-jobs-volume-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ empty($defer) ? 'display:none;' : '' }}">
                            <x-loader class="size-8 text-muted-foreground" />
                        </div>
                        @include('horizon.metrics.partials.chart-empty-overlay', ['overlayId' => 'metrics-empty-jobs-volume-chart'])
                        <div id="jobs-volume-last-24h-chart" class="chart-canvas"></div>
                    </div>
                </div>

                <div class="card min-w-0 overflow-hidden p-4">
                    <h3 class="text-section-title text-foreground mb-2">Failure rate over time (last 24h, %)</h3>
                    <div class="chart-panel">
                        <div id="metrics-loader-failure-rate-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ empty($defer) ? 'display:none;' : '' }}">
                            <x-loader class="size-8 text-muted-foreground" />
                        </div>
                        @include('horizon.metrics.partials.chart-empty-overlay', ['overlayId' => 'metrics-empty-failure-rate-chart'])
                        <div id="failure-rate-chart" class="chart-canvas"></div>
                    </div>
                </div>

                <div class="card min-w-0 overflow-hidden p-4">
                    <h3 class="text-section-title text-foreground mb-2">Job runtimes (last 24 hours, seconds)</h3>
                    <p class="text-xs text-muted-foreground mb-2">Each vertex is one job (finish time vs duration), connected in time order within Completed vs Failed.</p>
                    <div class="chart-panel">
                        <div id="metrics-loader-runtime-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ empty($defer) ? 'display:none;' : '' }}">
                            <x-loader class="size-8 text-muted-foreground" />
                        </div>
                        @include('horizon.metrics.partials.chart-empty-overlay', ['overlayId' => 'metrics-empty-runtime-chart'])
                        <div id="runtime-chart" class="chart-canvas"></div>
                    </div>
                </div>

                <div class="card min-w-0 overflow-hidden p-4">
                    <h3 class="text-section-title text-foreground mb-2">Queue wait by queue (max wait, top 12)</h3>
                    <div class="chart-panel">
                        <div id="metrics-loader-service-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ empty($defer) ? 'display:none;' : '' }}">
                            <x-loader class="size-8 text-muted-foreground" />
                        </div>
                        @include('horizon.metrics.partials.chart-empty-overlay', ['overlayId' => 'metrics-empty-service-chart'])
                        <div id="service-distribution-chart" class="chart-canvas"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Current workload</h3>
                    <p id="metrics-workload-summary" class="text-xs text-muted-foreground">{{ $workloadSummary ?? '' }}</p>
                </div>
                <x-table
                    resizable-key="horizon-metrics-queues"
                    column-ids="service,queue,jobs,processes,wait"
                    body-key="horizon-metrics-queues"
                    body-id="metrics-workload-body"
                    stream-patch-children
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                            <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                        </tr>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="5" columns="5" />
                    @else
                        @include('horizon.metrics.partials.workload-tbody', ['workloadRows' => $workloadRows ?? null])
                    @endif
                </x-table>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Supervisors</h3>
                    <p id="metrics-supervisors-summary" class="text-xs text-muted-foreground">{{ $supervisorsSummary ?? '' }}</p>
                </div>
                <x-table
                    resizable-key="horizon-metrics-supervisors"
                    column-ids="service,supervisor,jobs,processes,status"
                    body-key="horizon-metrics-supervisors"
                    body-id="metrics-supervisors-body"
                    stream-patch-children
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[140px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                            <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="processes">Processes</th>
                            <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="status">Status</th>
                        </tr>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="4" columns="5" />
                    @else
                        @include('horizon.metrics.partials.supervisors-tbody', ['supervisorsRows' => $supervisorsRows ?? null])
                    @endif
                </x-table>
            </div>
        </div>
    </div>
@endsection
