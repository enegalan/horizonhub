@php($horizonStreamMode = 'metrics')
@php($metricsBaseUrls = [
    'summary' => route('horizon.metrics.data.summary'),
    'jobRuntimesLast24h' => route('horizon.metrics.data.job-runtimes-last-24h'),
    'failureRate' => route('horizon.metrics.data.failure-rate-over-time'),
    'jobsVolumeLast24h' => route('horizon.metrics.data.jobs-volume-last-24h'),
    'supervisors' => route('horizon.metrics.data.supervisors'),
    'workload' => route('horizon.metrics.data.workload'),
])
@php($metricsChartData = [
    'jobsVolumeLast24h' => $jobsVolumeLast24h ?? ['xAxis' => [], 'completed' => [], 'failed' => []],
    'jobRuntimesLast24h' => $jobRuntimesLast24h ?? ['points' => []],
    'failureRateOverTime' => $failureRateOverTime ?? ['xAxis' => [], 'rate' => []],
])
@php($hasRuntimeChart = \is_array($jobRuntimesLast24h ?? null))
@php($hasFailureRateChart = \is_array($failureRateOverTime ?? null))
@php($hasJobsVolumeChart = \is_array($jobsVolumeLast24h ?? null))
@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonMetricsPage ? window.horizonMetricsPage({ baseUrls: {{ Js::from($metricsBaseUrls) }}, initialServiceIds: {{ Js::from(array_map('strval', $serviceIds ?? [])) }}, serviceShowBaseUrl: {{ Js::from(rtrim(url('/horizon/services'), '/')) }} }) : {}"
        x-init="typeof init === 'function' ? init() : null"
    >
        <script type="application/json" id="metrics-chart-data">@json($metricsChartData)</script>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label for="metrics-service-filter" class="label-muted text-sm">Filter by services</label>
            <x-multiselect
                id="metrics-service-filter"
                name="service_id"
                class="w-64"
                :selected="$serviceIds ?? []"
                placeholder="All services"
            >
                @foreach($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </x-multiselect>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <h3 class="label-muted">Jobs past minute</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-jobs-minute" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-jobs-minute" class="text-2xl font-semibold text-foreground">{{ $jobsPastMinute ?? '—' }}</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Jobs past hour</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-jobs-hour" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-jobs-hour" class="text-2xl font-semibold text-foreground">{{ $jobsPastHour ?? '—' }}</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Failed jobs (past 7 days)</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="metrics-loader-failed-seven" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="metrics-value-failed-seven" class="text-2xl font-semibold text-foreground">{{ $failedPastSevenDays ?? '—' }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-4">
            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Jobs per hour (last 24 hours)</h3>
                <div class="relative h-56">
                    <div id="metrics-loader-jobs-volume-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ $hasJobsVolumeChart ? 'display:none;' : '' }}">
                        <x-loader class="size-8 text-muted-foreground" />
                    </div>
                    <div id="jobs-volume-last-24h-chart" class="h-56"></div>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-1">Failure rate (last 24h)</h3>
                <div class="flex items-center gap-2 min-h-[2rem]">
                    <span id="metrics-loader-failure-rate" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <p id="metrics-value-failure-rate" class="text-xl font-semibold text-foreground">
                        @php($r = $failureRate24h ?? ['rate' => 0.0, 'processed' => 0, 'failed' => 0])
                        {{ $r['rate'] ?? 0 }}% <span class="text-xs font-normal text-muted-foreground">({{ $r['failed'] ?? 0 }} failed / {{ $r['processed'] ?? 0 }} processed)</span>
                    </p>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Failure rate over time (last 24h, %)</h3>
                <div class="relative h-56">
                    <div id="metrics-loader-failure-rate-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ $hasFailureRateChart ? 'display:none;' : '' }}">
                        <x-loader class="size-8 text-muted-foreground" />
                    </div>
                    <div id="failure-rate-chart" class="h-56"></div>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Job runtimes (last 24 hours, seconds)</h3>
                <p class="text-xs text-muted-foreground mb-2">Each vertex is one job (finish time vs duration), connected in time order within Completed vs Failed.</p>
                <div class="relative h-56">
                    <div id="metrics-loader-runtime-chart" class="absolute inset-0 flex items-center justify-center bg-muted/30 rounded" style="{{ $hasRuntimeChart ? 'display:none;' : '' }}">
                        <x-loader class="size-8 text-muted-foreground" />
                    </div>
                    <div id="runtime-chart" class="h-56"></div>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Current workload</h3>
                    <p id="metrics-workload-summary" class="text-xs text-muted-foreground">{{ $workloadSummary ?? '' }}</p>
                </div>
                <x-data-table
                    resizable-key="horizon-metrics-queues"
                    column-ids="service,queue,jobs,processes,wait"
                    body-key="horizon-metrics-queues"
                    body-id="metrics-workload-body"
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
                            <tr id="metrics-workload-empty" style="{{ (is_array($workloadRows ?? null) && \count($workloadRows) > 0) ? 'display:none;' : '' }}">
                                <td colspan="5" data-column-id="service">
                                    <div class="empty-state">
                                        <x-heroicon-o-queue-list class="empty-state-icon" />
                                        <p class="empty-state-title">No queues yet</p>
                                        <p class="empty-state-description">Queues will appear here once jobs are dispatched to your services.</p>
                                    </div>
                                </td>
                            </tr>
                            @foreach($workloadRows ?? [] as $row)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="service">
                                        @if(! empty($row['service_id']))
                                            <a href="{{ route('horizon.services.show', ['service' => $row['service_id']]) }}" class="link">{{ $row['service'] ?? '' }}</a>
                                        @else
                                            {{ $row['service'] ?? '' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">{{ $row['queue'] ?? '' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ isset($row['jobs']) ? (int) $row['jobs'] : 0 }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
                                        @if(isset($row['processes']) && $row['processes'] !== null)
                                            {{ (int) $row['processes'] }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
                                        @if(isset($row['wait']) && $row['wait'] !== null)
                                            @php($waitSeconds = (float) $row['wait'])
                                            <span data-wait-seconds="{{ $waitSeconds }}">
                                                {{ number_format($waitSeconds, 2, '.', '') }} s
                                            </span>
                                        @else
                                            –
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                </x-data-table>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Supervisors</h3>
                    <p id="metrics-supervisors-summary" class="text-xs text-muted-foreground">{{ $supervisorsSummary ?? '' }}</p>
                </div>
                <x-data-table
                    resizable-key="horizon-metrics-supervisors"
                    column-ids="service,supervisor,jobs,processes,status"
                    body-key="horizon-metrics-supervisors"
                    body-id="metrics-supervisors-body"
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
                            <tr id="metrics-supervisors-empty" style="{{ (is_array($supervisorsRows ?? null) && \count($supervisorsRows) > 0) ? 'display:none;' : '' }}">
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
                            @foreach($supervisorsRows ?? [] as $row)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="service">
                                        @if(! empty($row['service_id']))
                                            <a href="{{ route('horizon.services.show', ['service' => $row['service_id']]) }}" class="link">{{ $row['service'] ?? '' }}</a>
                                        @else
                                            {{ $row['service'] ?? '' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="supervisor">{{ $row['name'] ?? '' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground text-right" data-column-id="jobs">
                                        {{ isset($row['jobs']) ? (int) $row['jobs'] : 0 }}
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground text-right" data-column-id="processes">
                                        @if(isset($row['processes']) && $row['processes'] !== null)
                                            {{ (int) $row['processes'] }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-xs" data-column-id="status">
                                        @php($status = $row['status'] ?? 'stale')
                                        <span class="{{ $status === 'online' ? 'badge-success' : 'badge-warning' }}">
                                            {{ $status === 'online' ? 'Online' : 'Stale' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                </x-data-table>
            </div>
        </div>
    </div>
@endsection
