@extends('layouts.app')

@section('content')
    <div
        id="horizon-dashboard"
    >

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <h3 class="label-muted">Jobs past minute</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="dashboard-loader-jobs-minute" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="dashboard-value-jobs-minute" class="text-2xl font-semibold text-foreground">{{ $jobsPastMinute ?? '—' }}</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Jobs past hour</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="dashboard-loader-jobs-hour" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="dashboard-value-jobs-hour" class="text-2xl font-semibold text-foreground">{{ $jobsPastHour ?? '—' }}</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Failed jobs (past 7 days)</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="dashboard-loader-failed-seven" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <span id="dashboard-value-failed-seven" class="text-2xl font-semibold text-foreground">{{ $failedPastSevenDays ?? '—' }}</span>
                </div>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Services online</h3>
                <div class="mt-1 flex items-center gap-2 min-h-[2.5rem]">
                    <span id="dashboard-loader-services-online" style="display:none;"><x-loader class="size-5 shrink-0 text-muted-foreground" /></span>
                    <div id="dashboard-services-kpi-inner" class="flex items-center gap-2 min-h-[2.5rem]">
                        @include('horizon.dashboard.partials.kpi-services-online-inner')
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-section-title text-foreground">Services</h2>
                <a href="{{ route('horizon.services.index') }}" class="link text-sm" data-turbo-action="replace">Manage services</a>
            </div>
            <div id="dashboard-service-health-grid" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @include('horizon.dashboard.partials.service-health-grid', ['services' => $services ?? collect()])
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Recent alerts</h3>
                    <a href="{{ route('horizon.alerts.index') }}" class="link text-xs" data-turbo-action="replace">View all</a>
                </div>
                <x-table
                    resizable-key="horizon-dashboard-alerts"
                    column-ids="name,service,status,sent"
                    body-key="horizon-dashboard-alerts"
                    body-id="dashboard-recent-alerts-body"
                    stream-patch-children
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="name">Alert</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="status">Status</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="sent">Sent</th>
                        </tr>
                    </x-slot:head>
                    @include('horizon.dashboard.partials.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs ?? collect()])
                </x-table>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 class="text-section-title text-foreground">Current Workload</h3>
                    <a href="{{ route('horizon.queues.index') }}" class="link text-xs" data-turbo-action="replace">View queues</a>
                </div>
                <x-table
                    resizable-key="horizon-dashboard-workload"
                    column-ids="service,queue,jobs,processes,wait"
                    body-key="horizon-dashboard-workload"
                    body-id="dashboard-workload-summary-body"
                    stream-patch-children
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                            <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                        </tr>
                    </x-slot:head>
                    @include('horizon.dashboard.partials.workload-summary-tbody', ['workloadRows' => $workloadRows ?? []])
                </x-table>
            </div>
        </div>
    </div>
@endsection
