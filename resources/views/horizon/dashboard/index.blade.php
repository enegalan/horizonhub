@extends('layouts.app')

@section('content')
    <div
        id="horizon-dashboard"
        class="space-y-6"
    >
        <div class="card overflow-hidden">
            <div class="relative bg-gradient-to-br from-primary/10 via-card to-card py-4">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Jobs past minute</p>
                        <div class="mt-1 flex min-h-[2.5rem] items-center gap-2">
                            <span id="dashboard-value-jobs-minute" class="text-2xl font-semibold tabular-nums text-foreground">
                                @if(!empty($defer))
                                    <x-skeleton.text class="h-8 w-16" />
                                @else
                                    {{ $jobsPastMinute ?? '—' }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-sky-500/20 bg-sky-500/5 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">Jobs past hour</p>
                        <div class="mt-1 flex min-h-[2.5rem] items-center gap-2">
                            <span id="dashboard-value-jobs-hour" class="text-2xl font-semibold tabular-nums text-foreground">
                                @if(!empty($defer))
                                    <x-skeleton.text class="h-8 w-16" />
                                @else
                                    {{ $jobsPastHour ?? '—' }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-rose-500/20 bg-rose-500/5 px-4 py-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Failed jobs (7 days)</p>
                        <div class="mt-1 flex min-h-[2.5rem] items-center gap-2">
                            <span id="dashboard-value-failed-seven" class="text-2xl font-semibold tabular-nums text-foreground">
                                @if(!empty($defer))
                                    <x-skeleton.text class="h-8 w-16" />
                                @else
                                    {{ $failedPastSevenDays ?? '—' }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="rounded-lg border border-violet-500/20 bg-violet-500/5 px-4 py-3 sm:col-span-2 lg:col-span-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-violet-700 dark:text-violet-300">Services online</p>
                        <div class="mt-1 flex min-h-[2.5rem] items-center gap-2">
                            <div id="dashboard-services-kpi-inner" class="flex min-h-[2.5rem] items-center gap-2">
                                @if(!empty($defer))
                                    <x-skeleton.text class="size-4 shrink-0 rounded-full" />
                                    <x-skeleton.text class="h-8 w-20" />
                                @else
                                    @include('horizon.dashboard.partials.kpi-services-online-inner')
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>


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
                        @include('horizon.dashboard.partials.service-health-grid', ['services' => $services ?? collect()])
                    @endif
                </div>
            </div>

            <div class="grid lg:grid-cols-2 lg:divide-x lg:divide-border">
                <div class="flex min-w-0 flex-col border-b border-border lg:border-b-0">
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
                            @include('horizon.dashboard.partials.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs ?? collect()])
                        @endif
                    </x-table>
                </div>

                <div class="flex min-w-0 flex-col">
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
                            @include('horizon.dashboard.partials.workload-summary-tbody', ['workloadRows' => $workloadRows ?? []])
                        @endif
                    </x-table>
                </div>
            </div>
        </div>
    </div>
@endsection
