@extends('layouts.app')

@section('content')
    <div
        id="horizon-dashboard"
        class="space-y-6"
    >
        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Overview"
                title="Dashboard"
                description="Live health and workload across your connected Horizon services."
                class="border-b-0"
            />
        </div>

        <x-kpi-grid gradient>
            <x-stat-card label="Jobs past minute" tone="emerald" value-id="dashboard-value-jobs-minute">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $jobsPastMinute ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Jobs past hour" tone="sky" value-id="dashboard-value-jobs-hour">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $jobsPastHour ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Failed jobs (7 days)" tone="rose" value-id="dashboard-value-failed-seven">
                @if(!empty($defer))
                    <x-skeleton.text class="h-8 w-16" />
                @else
                    {{ $failedPastSevenDays ?? '—' }}
                @endif
            </x-stat-card>
            <x-stat-card label="Services online" tone="violet" class="sm:col-span-2 lg:col-span-1">
                <div id="dashboard-services-kpi-inner" class="flex min-h-[2.5rem] items-center gap-2">
                    @if(!empty($defer))
                        <x-skeleton.text class="size-4 shrink-0 rounded-full" />
                        <x-skeleton.text class="h-8 w-20" />
                    @else
                        @include('horizon.dashboard.partials.index.kpi-services-online')
                    @endif
                </div>
            </x-stat-card>
        </x-kpi-grid>

        <div class="card overflow-hidden">
            <div class="border-b border-border px-5 py-5 sm:px-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-section-title text-foreground">Services</h3>
                    <a href="{{ route('horizon.services.index') }}" class="link text-sm" data-turbo-action="replace">Manage services</a>
                </div>
                <div id="dashboard-service-health-grid" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @if(!empty($defer))
                        @for ($i = 0; $i < 6; $i++)
                            <div class="card overflow-hidden p-4">
                                <div class="skeleton h-1 w-full rounded-full" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                                <div class="mt-3 flex items-start gap-3">
                                    <div class="min-w-0 flex-1 space-y-2">
                                        <div class="skeleton h-4 max-w-[220px]" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                                        <div class="skeleton h-3 w-1/2" style="--skeleton-delay: {{ ($i * 60) + 160 }}ms"></div>
                                    </div>
                                    <div class="skeleton size-4 shrink-0" style="--skeleton-delay: {{ ($i * 60) + 120 }}ms"></div>
                                </div>
                            </div>
                        @endfor
                    @else
                        @include('horizon.dashboard.partials.index.service-health-grid', ['services' => $services ?? null])
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card flex min-w-0 flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
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
                            <th class="table-header min-w-[120px] px-4 py-2.5" data-column-id="name">Alert</th>
                            <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="service">Service</th>
                            <th class="table-header min-w-[80px] px-4 py-2.5" data-column-id="status">Status</th>
                            <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="sent">Sent</th>
                        </tr>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="5" columns="4" />
                    @else
                        @include('horizon.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs ?? null])
                    @endif
                </x-table>
            </div>

            <div class="card flex min-w-0 flex-col overflow-hidden">
                <div class="flex items-center justify-between border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">Current workload</h3>
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
                            <th class="table-header min-w-[120px] px-4 py-2.5" data-column-id="service">Service</th>
                            <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                            <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                        </tr>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="5" columns="5" />
                    @else
                        @include('horizon.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $workloadRows ?? []])
                    @endif
                </x-table>
            </div>
        </div>
    </div>
@endsection
