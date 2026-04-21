@extends('layouts.app')

@section('content')
    <div
        id="horizon-service-dashboard"
        x-data="{
            showDeleteServiceModal: false,
            openDeleteServiceModal() {
                this.showDeleteServiceModal = true;
            },
            closeDeleteServiceModal() {
                this.showDeleteServiceModal = false;
            },
            confirmDeleteService() {
                this.$refs.deleteServiceForm.requestSubmit();
                this.closeDeleteServiceModal();
            }
        }"
    >
        <p class="mb-3 text-xs text-muted-foreground">
            <a href="{{ route('horizon.jobs.index') }}" class="link" data-turbo-action="replace">Jobs</a> /
            <a href="{{ route('horizon.services.index') }}" class="link" data-turbo-action="replace">Services</a> /
            <span class="text-foreground">{{ $service->name }}</span>
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-2">
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
            @endphp
            <div class="mr-4 inline-flex items-center gap-2 rounded-md border border-border bg-muted/30 px-3 py-1.5">
                <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $serviceStatusColor }}" title="{{ $serviceStatusLabel }}" aria-label="{{ $serviceStatusLabel }}"></span>
                <span class="text-xs text-muted-foreground">
                    Status: <span class="font-medium text-foreground">{{ $serviceStatusLabel }}</span>
                </span>
            </div>
            @php
                $dashboardUrl = $service->getPublicUrl().config('horizonhub.horizon_paths.dashboard');
            @endphp
            <x-button
                variant="ghost"
                type="button"
                onclick="window.open('{{ $dashboardUrl }}', '_blank')"
                class="h-8 min-h-8 p-2"
                aria-label="Open Horizon dashboard"
                title="Open Horizon dashboard"
            >
                <x-heroicon-o-window class="size-4" />
            </x-button>
            <form method="POST" action="{{ route('horizon.services.test-connection', $service) }}">
                @csrf
                <x-button
                    variant="ghost"
                    type="submit"
                    class="h-8 min-h-8 p-2"
                    aria-label="Test connection"
                    title="Test connection"
                >
                    <x-heroicon-o-signal class="size-4" />
                </x-button>
            </form>
            <x-button
                variant="ghost"
                type="button"
                onclick="window.location.href='{{ route('horizon.services.edit', $service) }}'"
                class="h-8 min-h-8 p-2"
                aria-label="Edit service"
                title="Edit service"
            >
                <x-heroicon-o-pencil-square class="size-4" />
            </x-button>
            <x-button
                variant="ghost"
                type="button"
                class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                aria-label="Delete service"
                title="Delete service"
                @click="openDeleteServiceModal()"
            >
                <x-heroicon-o-trash class="size-4" />
            </x-button>
        </div>

        <div id="service-show-stats-row-1" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            @include('horizon.services.partials.show-stats-row-1-inner')
        </div>

        <div id="service-show-stats-row-2" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            @include('horizon.services.partials.show-stats-row-2-inner')
        </div>

        <div class="card mb-4 p-4">
            <h3 class="text-section-title text-foreground mb-2">Supervisors</h3>
            <div id="service-show-supervisors-panel">
                @include('horizon.services.partials.show-supervisors-panel-inner')
            </div>
        </div>

        <div class="card mb-4">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
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
                @include('horizon.services.partials.show-workload-tbody', ['workloadQueues' => $workloadQueues])
            </x-table>
        </div>

        <div id="service-show-supervisor-groups">
            @include('horizon.services.partials.show-supervisor-groups')
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
            ])
        </div>
        </x-turbo::frame>

        @include('horizon.services.partials.delete-service-confirm-modal', ['detailService' => $service])
    </div>
@endsection
