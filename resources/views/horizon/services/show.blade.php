@extends('layouts.app')

@section('content')
    @php
        $workloadQueues ??= collect();
        $filters ??= ['search' => ''];
    @endphp
    <div
        id="horizon-service-dashboard"
        x-data="window.horizonDeleteConfirm ? window.horizonDeleteConfirm('Service', { listMode: false }) : {}"
    >
        <x-breadcrumbs :items="[
            ['label' => 'Jobs', 'url' => route('horizon.jobs.index')],
            ['label' => 'Services', 'url' => route('horizon.services.index')],
            ['label' => $service->name],
        ]" />

        <x-session-flash class="mb-4 rounded-md border" />

        @if(! $service->enabled)
            <div class="mb-4 rounded-md border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
                This service is disabled. {{ config('app.name') }} will not poll its API or include it in metrics and alerts until you enable it again from the
                <a href="{{ route('horizon.services.index') }}" class="link font-medium" data-turbo-action="replace">services list</a>.
            </div>
        @endif

        @php
            if ($service->status === 'online') {
                $serviceStatusColor = 'bg-emerald-500';
                $serviceStatusLabel = 'Online';
            } elseif ($service->status === 'stand_by') {
                $serviceStatusColor = 'bg-amber-500';
                $serviceStatusLabel = 'Stand-by';
            } else {
                $serviceStatusColor = 'bg-red-500';
                $serviceStatusLabel = 'Offline';
            }
            $dashboardUrl = $service->getPublicUrl().config('horizonhub.horizon_paths.dashboard');
        @endphp
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="inline-flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-1.5">
                <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $serviceStatusColor }}" title="{{ $serviceStatusLabel }}" aria-label="{{ $serviceStatusLabel }}"></span>
                <span class="text-xs text-muted-foreground">
                    Status: <span class="font-medium text-foreground">{{ $serviceStatusLabel }}</span>
                </span>
            </div>
            @include('horizon.services.partials.service-tags', ['tags' => $service->tags ?? []])
            <div class="inline-flex flex-wrap items-center gap-1 rounded-lg border border-border bg-card p-1">
                <x-button
                    variant="ghost"
                    type="button"
                    onclick="window.open('{{ $dashboardUrl }}', '_blank')"
                    class="h-8 gap-1.5 px-2 sm:px-3"
                    aria-label="Open Horizon dashboard"
                >
                    <x-heroicon-o-window class="size-4 shrink-0" />
                    <span class="hidden text-xs sm:inline">Horizon</span>
                </x-button>
                <form method="POST" action="{{ route('horizon.services.test-connection', $service) }}" class="inline-flex">
                    @csrf
                    <x-button
                        variant="ghost"
                        type="submit"
                        class="h-8 gap-1.5 px-2 sm:px-3"
                        aria-label="Test connection"
                    >
                        <x-heroicon-o-signal class="size-4 shrink-0" />
                        <span class="hidden text-xs sm:inline">Test</span>
                    </x-button>
                </form>
                <x-form-drawer-link
                    :href="route('horizon.services.edit', $service)"
                    variant="ghost"
                    class="h-8 gap-1.5 px-2 sm:px-3"
                    aria-label="Edit service"
                >
                    <x-heroicon-o-pencil-square class="size-4 shrink-0" />
                    <span class="hidden text-xs sm:inline">Edit</span>
                </x-form-drawer-link>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 gap-1.5 px-2 text-destructive hover:text-destructive sm:px-3"
                    aria-label="Delete service"
                    @click="openDeleteServiceModal()"
                >
                    <x-heroicon-o-trash class="size-4 shrink-0" />
                    <span class="hidden text-xs sm:inline">Delete</span>
                </x-button>
            </div>
        </div>

        <div id="service-show-stats-row-1" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            @if(!empty($defer))
                @include('horizon.services.partials.show-stats-row-1-skeleton')
            @else
                @include('horizon.services.partials.show-stats-row-1-inner')
            @endif
        </div>

        <div id="service-show-stats-row-2" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            @if(!empty($defer))
                @include('horizon.services.partials.show-stats-row-2-skeleton')
            @else
                @include('horizon.services.partials.show-stats-row-2-inner')
            @endif
        </div>

        <div class="card mb-4 p-4">
            <h3 class="text-section-title text-foreground mb-2">Supervisors</h3>
            <div id="service-show-supervisors-panel">
                @if(!empty($defer))
                    @include('horizon.services.partials.show-supervisors-panel-skeleton')
                @else
                    @include('horizon.services.partials.show-supervisors-panel-inner')
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                <h3 class="text-section-title text-foreground">Current workload</h3>
                <p id="service-show-workload-count" class="text-xs text-muted-foreground">
                    @if($workloadQueues->count() > 0)
                        {{ $workloadQueues->count() }} queue(s)
                    @endif
                </p>
            </div>
            <x-table
                resizable-key="horizon-service-queues"
                column-ids="queue,jobs,processes,wait"
                body-key="horizon-service-queues"
                body-id="service-show-workload-body"
                stream-patch-children
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                        <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                        <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                    </tr>
                </x-slot:head>
                @if(!empty($defer))
                    <x-skeleton.table-rows rows="5" columns="4" />
                @else
                    @include('horizon.services.partials.show-workload-tbody', ['workloadQueues' => $workloadQueues])
                @endif
            </x-table>
        </div>

        <div id="service-show-supervisor-groups">
            @if(!empty($defer))
                @include('horizon.services.partials.show-supervisor-groups-skeleton')
            @else
                @include('horizon.services.partials.show-supervisor-groups')
            @endif
        </div>

        <x-turbo::frame id="service-jobs">
        <div class="card">
            <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="space-y-2">
                    <x-input-label for="service-jobs-search">Search</x-input-label>
                    <form method="GET" action="{{ route('horizon.services.show', $service) }}" id="service-jobs-search" class="flex gap-2" data-turbo-frame="service-jobs">
                        <x-text-input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Queue, job or UUID"
                            class="w-52"
                        />
                        <x-button type="submit" class="shrink-0">Search</x-button>
                    </form>
                </div>
            </div>
            @include('horizon.jobs.partials.job-list-collapsible-stack', [
                'jobsProcessing' => $jobsProcessing,
                'jobsProcessed' => $jobsProcessed,
                'jobsFailed' => $jobsFailed,
                'showServiceColumn' => false,
                'pageService' => $service,
                'columnIds' => 'uuid,queue,job,attempts,queued_at,delayed_until,processed,failed_at,runtime,actions',
                'resizablePrefix' => 'horizon-service-dashboard-jobs',
                'defer' => $defer ?? false,
            ])
        </div>
        </x-turbo::frame>

        @include('horizon.services.partials.delete-service-confirm-modal', ['detailService' => $service])
    </div>
@endsection
