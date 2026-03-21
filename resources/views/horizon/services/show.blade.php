@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonServiceDashboard ? window.horizonServiceDashboard() : {}"
        x-init="typeof init === 'function' ? init() : null"
        data-horizon-service-dashboard-root="1"
    >
        <p class="mb-3 text-xs text-muted-foreground">
            <a href="{{ route('horizon.index') }}" class="link">Jobs</a> /
            <a href="{{ route('horizon.services.index') }}" class="link">Services</a> /
            <span class="text-foreground">{{ $service->name }}</span>
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            @php
                $serviceStatus = $service->status ?? null;
                if ($serviceStatus === 'online') {
                    $serviceStatusColor = 'bg-emerald-500';
                    $serviceStatusLabel = 'Online';
                } elseif ($serviceStatus === 'offline') {
                    $serviceStatusColor = 'bg-red-500';
                    $serviceStatusLabel = 'Offline';
                } else {
                    $serviceStatusColor = 'bg-slate-400';
                    $serviceStatusLabel = 'Unknown';
                }
            @endphp
            <div class="mr-4 inline-flex items-center gap-2 rounded-md border border-border bg-muted/30 px-3 py-1.5">
                <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $serviceStatusColor }}" title="{{ $serviceStatusLabel }}" aria-label="{{ $serviceStatusLabel }}"></span>
                <span class="text-xs text-muted-foreground">
                    Status: <span class="font-medium text-foreground">{{ $serviceStatusLabel }}</span>
                </span>
            </div>
            @php
                $dashboardBase = $service->public_url ?: $service->base_url;
            @endphp
            @if($dashboardBase)
                <x-button
                    variant="ghost"
                    type="button"
                    onclick="window.open('{{ rtrim($dashboardBase, '/') . \config('horizonhub.horizon_paths.dashboard') }}', '_blank')"
                    class="h-8 min-h-8 p-2"
                    aria-label="Open Horizon dashboard"
                    title="Open Horizon dashboard"
                >
                    <x-heroicon-o-window class="size-4" />
                </x-button>
            @endif
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
            <form method="POST" action="{{ route('horizon.services.destroy', $service) }}" onsubmit="return confirm('Delete service {{ $service->name }}?');">
                @csrf
                @method('DELETE')
                <x-button
                    variant="ghost"
                    type="submit"
                    class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                    aria-label="Delete service"
                    title="Delete service"
                >
                    <x-heroicon-o-trash class="size-4" />
                </x-button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <h3 class="label-muted">Jobs past minute</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastMinute) }}</p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Jobs past hour</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastHour) }}</p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Failed (past 7 days)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($failedPastSevenDays) }}</p>
            </div>
            <div class="card p-4">
                @php
                    $hs = \strtolower((string) $horizonStatus);
                    if ($hs === 'active' || $hs === 'running') {
                        $horizonStatusColor = 'bg-emerald-500';
                        $horizonStatusLabel = 'Active';
                    } elseif ($hs === 'inactive') {
                        $horizonStatusColor = 'bg-amber-500';
                        $horizonStatusLabel = 'Inactive';
                    } else {
                        $horizonStatusColor = 'bg-slate-400';
                        $horizonStatusLabel = $horizonStatus !== null && $horizonStatus !== '' ? (string) $horizonStatus : 'Unknown';
                    }
                @endphp
                <h3 class="label-muted">Status</h3>
                <div class="mt-1 flex items-center gap-2">
                    <span
                        class="inline-flex shrink-0 size-2.5 rounded-full {{ $horizonStatusColor }}"
                        title="Horizon {{ $horizonStatusLabel }}"
                        aria-label="Horizon {{ $horizonStatusLabel }}"
                    ></span>
                    <p class="text-2xl font-semibold text-foreground">
                        {{ $horizonStatusLabel }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="card p-4">
                <h3 class="label-muted">Total processes</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ $totalProcesses !== null ? number_format($totalProcesses) : '–' }}
                </p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Max wait time (s)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ $maxWaitTimeSeconds !== null ? number_format($maxWaitTimeSeconds, 2) : '–' }}
                </p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Max runtime</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ $queueWithMaxRuntime !== null ? $queueWithMaxRuntime : '–' }}
                </p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Max throughput</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">
                    {{ $queueWithMaxThroughput !== null ? $queueWithMaxThroughput : '–' }}
                </p>
            </div>
        </div>

        <div class="card mb-4 p-4">
            <h3 class="text-section-title text-foreground mb-2">Supervisors</h3>
            @if(isset($supervisors) && $supervisors->isNotEmpty())
                <div class="space-y-2">
                    @foreach($supervisors as $supervisor)
                        @php
                            $apiStatus = $supervisor->status ?? '';
                            if (\strtolower($apiStatus) === 'running') {
                                $statusColor = 'bg-emerald-500';
                                $statusTitle = 'Online';
                                $statusBlink = false;
                            } elseif (\strtolower($apiStatus) === 'inactive' || $apiStatus !== '') {
                                $statusColor = 'bg-amber-500';
                                $statusTitle = $apiStatus !== '' ? \ucfirst($apiStatus) : 'Unknown';
                                $statusBlink = \strtolower($apiStatus) === 'inactive';
                            } else {
                                $statusColor = 'bg-slate-400';
                                $statusTitle = 'Unknown';
                                $statusBlink = false;
                            }
                        @endphp
                        <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $statusColor }} @if($statusBlink) animate-pulse @endif" title="{{ $statusTitle }}" aria-label="{{ $statusTitle }}"></span>
                                <span class="font-mono text-sm text-foreground">{{ $supervisor->name }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-muted-foreground">
                    Supervisor data is not available. Run
                    <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">php artisan horizon</code>
                    on the service,
                    ensure the service is running and reachable, and wait a few seconds for supervisor heartbeats.
                </p>
            @endif
        </div>

        <div class="card mb-4">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h3 class="text-section-title text-foreground">Current workload</h3>
                @if($workloadQueues->count() > 0)
                    <p class="text-xs text-muted-foreground">{{ $workloadQueues->count() }} queue(s)</p>
                @endif
            </div>
            <x-data-table
                resizable-key="horizon-service-queues"
                column-ids="queue,jobs,processes,wait"
                body-key="horizon-service-queues"
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                        <th class="table-header px-4 py-2.5" data-column-id="processes">Processes</th>
                        <th class="table-header px-4 py-2.5" data-column-id="wait">Wait</th>
                    </tr>
                </x-slot:head>
                        @forelse($workloadQueues as $row)
                            <tr class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
                                    {{ $row->queue }}
                                </td>
                                <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">
                                    {{ number_format($row->jobs) }}
                                </td>
                                <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
                                    {{ $row->processes !== null ? number_format($row->processes) : '–' }}
                                </td>
                                <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="wait">
                                    @if($row->wait !== null)
                                        <span data-wait-seconds="{{ $row->wait }}">{{ number_format($row->wait, 2) }} s</span>
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" data-column-id="queue">
                                    <div class="empty-state">
                                        <x-heroicon-o-queue-list class="empty-state-icon" />
                                        <p class="empty-state-title">No queues for this service yet</p>
                                        <p class="empty-state-description">Queues will appear here once jobs are dispatched to this service.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
            </x-data-table>
        </div>

        @if(isset($supervisorGroups) && $supervisorGroups->isNotEmpty())
            @foreach($supervisorGroups as $groupName => $groupSupervisors)
                <div class="card mb-4">
                    <div class="flex items-center justify-between border-b border-border px-4 py-3">
                        <h3 class="text-section-title text-foreground">{{ $groupName }}</h3>
                        <p class="text-xs text-muted-foreground">{{ $groupSupervisors->count() }} supervisor(s)</p>
                    </div>
                    <x-data-table
                        resizable-key="horizon-service-supervisors-{{ \Illuminate\Support\Str::slug($groupName) }}"
                        column-ids="supervisor,connection,queues,processes,balancing"
                        body-key="horizon-service-supervisors-{{ \Illuminate\Support\Str::slug($groupName) }}"
                    >
                        <x-slot:head>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                                <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="connection">Connection</th>
                                <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="queues">Queues</th>
                                <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="processes">Processes</th>
                                <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="balancing">Balancing</th>
                            </tr>
                        </x-slot:head>
                                @foreach($groupSupervisors as $supervisor)
                                    <tr class="transition-colors hover:bg-muted/30">
                                        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="supervisor">
                                            {{ $supervisor->name }}
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="connection">
                                            {{ $supervisor->connection !== '' ? $supervisor->connection : '–' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="queues">
                                            {{ $supervisor->queues !== '' ? $supervisor->queues : '–' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
                                            {{ $supervisor->processes !== null ? number_format($supervisor->processes) : '–' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="balancing">
                                            {{ $supervisor->balancing !== '' ? $supervisor->balancing : '–' }}
                                        </td>
                                    </tr>
                                @endforeach
                    </x-data-table>
                </div>
            @endforeach
        @endif

        <div class="card">
            <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="space-y-2">
                    <x-input-label for="service-jobs-search">Search</x-input-label>
                    <form method="GET" action="{{ route('horizon.services.show', $service) }}" id="service-jobs-search" class="flex gap-2">
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
                'columnIds' => 'uuid,queue,job,attempts,queued_at,processed,failed_at,runtime,actions',
                'resizablePrefix' => 'horizon-service-dashboard-jobs',
            ])
        </div>
    </div>
@endsection
