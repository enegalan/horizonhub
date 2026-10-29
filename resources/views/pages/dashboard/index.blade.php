@php
    $hasEnabledServices = \App\Models\Service::query()->enabled()->exists();
@endphp

@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="card overflow-hidden">
            <div
                id="dashboard-stats"
                class="grid grid-cols-1 divide-y divide-border sm:grid-cols-2 sm:divide-x lg:grid-cols-4 lg:divide-y-0"
            >
                @if($hasEnabledServices)
                    @for($i = 0; $i < 4; $i++)
                        <div class="animate-pulse space-y-3 p-4 sm:p-5">
                            <div class="h-3 w-28 rounded bg-muted"></div>
                            <div class="h-8 w-16 rounded bg-muted"></div>
                            <div class="space-y-2 pt-1">
                                <div class="h-3 w-full rounded bg-muted"></div>
                                <div class="h-3 w-3/4 rounded bg-muted"></div>
                            </div>
                        </div>
                    @endfor
                @else
                    @include('pages.dashboard.partials.index.stats', ['stats' => []])
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <h2 class="text-sm font-semibold text-foreground">Services</h2>
                    <div id="dashboard-services-kpi-inner" class="flex items-center gap-2 text-sm" aria-label="Services online">
                        @include('pages.dashboard.partials.index.kpi-services-online', [
                            'offlineCount' => 0,
                            'onlineCount' => 0,
                            'servicesCount' => 0,
                            'standByCount' => 0,
                        ])
                    </div>
                </div>
                <a href="{{ route('horizon.services.index') }}" class="link text-sm" data-turbo-action="replace">Manage</a>
            </div>
            <div id="dashboard-service-health-grid">
                @include('pages.dashboard.partials.index.service-health-grid', [
                    'services' => [],
                    'defer' => $hasEnabledServices,
                ])
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="text-sm font-semibold text-foreground">Supervisors</h2>
                <a href="{{ route('horizon.instances.index') }}" class="link text-sm" data-turbo-action="replace">View all</a>
            </div>
            <div
                id="dashboard-supervisor-stats"
                class="grid grid-cols-1 divide-y divide-border border-b border-border sm:grid-cols-3 sm:divide-x sm:divide-y-0"
            >
                @if($hasEnabledServices)
                    @for($i = 0; $i < 3; $i++)
                        <div class="animate-pulse space-y-2 px-4 py-4 text-center sm:px-5">
                            <div class="mx-auto h-3 w-28 rounded bg-muted"></div>
                            <div class="mx-auto h-8 w-12 rounded bg-muted"></div>
                        </div>
                    @endfor
                @else
                    @include('pages.dashboard.partials.index.supervisor-stats', ['supervisorStats' => []])
                @endif
            </div>
            <div id="dashboard-supervisors" class="space-y-4 p-4">
                @include('pages.partials.master-groups', [
                    'masterGroups' => [],
                    'showServiceLink' => true,
                    'emptyTitle' => 'No supervisors',
                    'emptyDescription' => $hasEnabledServices
                        ? 'Waiting for live supervisor data…'
                        : 'Enable a service to see supervisor processes.',
                    'defer' => $hasEnabledServices,
                ])
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">Recent alerts</h2>
                    <a href="{{ route('horizon.alerts.index') }}" class="link text-sm" data-turbo-action="replace">View all</a>
                </div>
                <div
                    id="dashboard-alert-stats"
                    class="grid grid-cols-1 divide-y divide-border border-b border-border sm:grid-cols-3 sm:divide-x sm:divide-y-0"
                >
                    @include('pages.dashboard.partials.index.alert-stats', ['alertStats' => []])
                </div>
                <div id="dashboard-alerts-list" class="divide-y divide-border">
                    @include('pages.dashboard.partials.index.recent-alerts-list', [
                        'recentAlertLogs' => [],
                        'defer' => true,
                    ])
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-foreground">Current workload</h2>
                    <a href="{{ route('horizon.queues.index') }}" class="link text-sm" data-turbo-action="replace">Queues</a>
                </div>
                <div
                    id="dashboard-workload-stats"
                    class="grid grid-cols-1 divide-y divide-border border-b border-border sm:grid-cols-3 sm:divide-x sm:divide-y-0"
                >
                    @if($hasEnabledServices)
                        @for($i = 0; $i < 3; $i++)
                            <div class="animate-pulse space-y-2 px-4 py-4 text-center sm:px-5">
                                <div class="mx-auto h-3 w-24 rounded bg-muted"></div>
                                <div class="mx-auto h-8 w-12 rounded bg-muted"></div>
                            </div>
                        @endfor
                    @else
                        @include('pages.dashboard.partials.index.workload-stats', ['workloadStats' => []])
                    @endif
                </div>
                <div id="dashboard-workload-list" class="divide-y divide-border">
                    @include('pages.dashboard.partials.index.workload-list', [
                        'workloadRows' => [],
                        'defer' => $hasEnabledServices,
                    ])
                </div>
            </div>
        </div>
    </div>
@endsection
