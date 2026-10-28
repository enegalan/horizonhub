@extends('layouts.app')

@section('content')
    <div
        id="horizon-dashboard"
        class="space-y-6"
    >
        <div class="card overflow-hidden">
            <div class="grid grid-cols-2 divide-x divide-y divide-border lg:grid-cols-4 lg:divide-y-0">
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jobs past minute</p>
                    <div id="dashboard-value-jobs-minute" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-16" />
                        @else
                            {{ $jobsPastMinute ?? '—' }}
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jobs past hour</p>
                    <div id="dashboard-value-jobs-hour" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-16" />
                        @else
                            {{ $jobsPastHour ?? '—' }}
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Failed jobs (7 days)</p>
                    <div id="dashboard-value-failed-seven" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-16" />
                        @else
                            {{ $failedPastSevenDays ?? '—' }}
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Services online</p>
                    <div id="dashboard-services-kpi-inner" class="mt-1 flex min-h-10 items-center gap-2 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="size-4 shrink-0 rounded-full" />
                            <x-skeleton.text class="h-8 w-20" />
                        @else
                            @include('pages.dashboard.partials.index.kpi-services-online')
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-border border-t border-border bg-muted/10 lg:grid-cols-4 lg:divide-y-0">
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Pending jobs</p>
                    <div id="dashboard-value-pending" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-20" />
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Silenced jobs</p>
                    <div id="dashboard-value-silenced" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-20" />
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Recent batches</p>
                    <div id="dashboard-value-batches" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-20" />
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="min-w-0 px-5 py-4 sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Worker processes</p>
                    <div id="dashboard-value-processes" class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                        @if(!empty($defer))
                            <x-skeleton.text class="h-8 w-20" />
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>

            <div id="dashboard-limits-summary" class="border-t border-border">
                @if(!empty($defer))
                    <div class="min-w-0 px-5 py-4 sm:px-6">
                        <div class="skeleton h-3 w-24"></div>
                        <div class="mt-2 skeleton h-7 w-20" style="--skeleton-delay: 80ms"></div>
                    </div>
                @else
                    @include('pages.dashboard.partials.index.limits-summary', ['summary' => []])
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                <h3 class="text-section-title text-foreground">Services</h3>
                <a href="{{ route('horizon.services.index') }}" class="link text-xs" data-turbo-action="replace">Manage services</a>
            </div>
            <div
                id="dashboard-service-health-grid"
                class="max-h-96 divide-y divide-border overflow-y-auto"
            >
                @if(!empty($defer))
                    @for ($i = 0; $i < 6; $i++)
                        <div class="flex flex-wrap items-center gap-2 px-4 py-2.5 sm:gap-3 sm:px-5">
                            <div class="skeleton size-2 shrink-0 rounded-full" style="--skeleton-delay: {{ $i * 40 }}ms"></div>
                            <div class="skeleton h-4 max-w-48 flex-1" style="--skeleton-delay: {{ ($i * 40) + 60 }}ms"></div>
                            <div class="skeleton h-5 w-16 shrink-0" style="--skeleton-delay: {{ ($i * 40) + 100 }}ms"></div>
                            <div class="skeleton h-5 w-28 shrink-0" style="--skeleton-delay: {{ ($i * 40) + 140 }}ms"></div>
                        </div>
                    @endfor
                @else
                    @include('pages.dashboard.partials.index.service-health-grid', ['services' => $services ?? null])
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                <h3 class="text-section-title text-foreground">Supervisors</h3>
                <a href="{{ route('horizon.instances.index') }}" class="link text-xs" data-turbo-action="replace">View instances</a>
            </div>
            <div id="dashboard-supervisors" class="space-y-4 p-4 sm:p-5">
                @if(!empty($defer))
                    <div class="grid gap-4 xl:grid-cols-2">
                        @for ($i = 0; $i < 2; $i++)
                            <div class="card overflow-hidden p-4">
                                <div class="skeleton h-4 w-40" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                                <div class="mt-3 space-y-2">
                                    <div class="skeleton h-3 w-full" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                                    <div class="skeleton h-3 w-4/5" style="--skeleton-delay: {{ ($i * 60) + 140 }}ms"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                @else
                    @include('pages.partials.master-groups', [
                        'emptyDescription' => 'No supervisors were reported by the connected Horizon services.',
                        'emptyTitle' => 'No supervisors',
                        'masterGroups' => [],
                        'showServiceLink' => true,
                    ])
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card flex min-w-0 flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">Recent alerts</h3>
                    <a href="{{ route('horizon.alerts.index') }}" class="link text-xs" data-turbo-action="replace">View all</a>
                </div>
                <x-table
                    id="horizon-dashboard-alerts"
                    stream-patch-children
                >
                    <x-slot:head>
                        <x-table.th column="name" class="min-w-30">Alert</x-table.th>
                        <x-table.th column="service" class="min-w-25">Service</x-table.th>
                        <x-table.th column="status" class="min-w-20">Status</x-table.th>
                        <x-table.th column="sent" class="min-w-25">Sent</x-table.th>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="5" columns="4" />
                    @else
                        @include('pages.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs ?? null])
                    @endif
                </x-table>
            </div>

            <div class="card flex min-w-0 flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">Current workload</h3>
                    <a href="{{ route('horizon.queues.index') }}" class="link text-xs" data-turbo-action="replace">View queues</a>
                </div>
                <x-table
                    id="horizon-dashboard-workload"
                    stream-patch-children
                >
                    <x-slot:head>
                        <x-table.th column="service" class="min-w-30">Service</x-table.th>
                        <x-table.th column="queue" class="min-w-25">Queue</x-table.th>
                        <x-table.th column="jobs">Jobs</x-table.th>
                        <x-table.th column="processes">Processes</x-table.th>
                        <x-table.th column="wait">Wait</x-table.th>
                    </x-slot:head>
                    @if(!empty($defer))
                        <x-skeleton.table-rows rows="5" columns="5" />
                    @else
                        @include('pages.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $workloadRows ?? []])
                    @endif
                </x-table>
            </div>
        </div>
    </div>
@endsection
