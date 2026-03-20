@extends('layouts.app')

@section('content')
    <div
        class="card"
        x-data="window.horizonJobsPage ? window.horizonJobsPage({
            failedListUrl: '{{ route('horizon.jobs.failed') }}',
            retryBatchUrl: '{{ route('horizon.jobs.retry-batch') }}',
            jobsPerPage: {{ \config('horizonhub.jobs_per_page') }},
        }) : {}"
        x-init="if (typeof init === 'function') { init(); }"
    >
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-2">
                <x-input-label>Service</x-input-label>
                <form method="GET" action="{{ route('horizon.index') }}">
                    <x-select name="serviceFilter" class="w-44" onchange="this.form.submit()" placeholder="All">
                        @foreach($services as $s)
                            <option value="{{ $s->id }}" @selected(($filters['serviceFilter'] ?? '') === (string) $s->id)>{{ $s->name }} ({{ $s->status }})</option>
                        @endforeach
                    </x-select>
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                </form>
            </div>
            <div class="space-y-2">
                <x-input-label>Search</x-input-label>
                <form method="GET" action="{{ route('horizon.index') }}">
                    <x-text-input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Queue, job or UUID"
                        class="w-56"
                    />
                    <input type="hidden" name="serviceFilter" value="{{ $filters['serviceFilter'] ?? '' }}">
                </form>
            </div>
            <div class="flex items-end gap-2 ml-auto">
                <x-button type="button" class="h-9 text-sm" @click="openRetryModal()">
                    Retry failed jobs
                </x-button>
            </div>
        </div>
        <div
            class="space-y-6 pt-4"
            x-data="{
                sectionOpen: { processing: true, processed: true, failed: true },
                init() {
                    try {
                        const s = localStorage.getItem('horizon_jobs_sections');
                        if (s) { this.sectionOpen = Object.assign({}, this.sectionOpen, JSON.parse(s)); }
                    } catch (e) {}
                },
                onToggle(section, e) {
                    this.sectionOpen[section] = e.target.open;
                    localStorage.setItem('horizon_jobs_sections', JSON.stringify(this.sectionOpen));
                }
            }"
        >
            <details
                class="group border-b border-border pb-4"
                :open="sectionOpen.processing"
                @toggle="onToggle('processing', $event)"
            >
                <summary class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 text-section-title text-foreground [&::-webkit-details-marker]:hidden">
                    <x-heroicon-o-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
                    <span>Processing</span>
                </summary>
                <div class="pt-2">
                <x-data-table
                    resizable-key="horizon-job-list-processing"
                    column-ids="uuid,service,queue,job,attempts,queued_at,processed,failed_at,runtime,actions"
                    body-key="horizon-job-list-processing"
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="attempts">Attempts</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queued_at">Queued at</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="actions">Actions</th>
                        </tr>
                    </x-slot:head>
                            @forelse($jobsProcessing as $job)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
                                        <a class="link" href="{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}">
                                            {{ $job->uuid }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate max-w-[180px]" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue">{{ $job->queue }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->uuid }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="attempts">
                                        @php $attempts = $job->attempts; $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–'; @endphp
                                        {{ $attemptsDisplay }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at" data-datetime="{{ $job->queued_at?->format('c') ?? '' }}">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                                    <td class="px-4 py-2.5" data-column-id="actions">
                                        <div class="flex items-center gap-1">
                                            <x-button
                                                variant="secondary"
                                                class="h-8 min-h-8 p-2 rounded-md"
                                                aria-label="View"
                                                title="View"
                                                onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}'"
                                            >
                                                <x-heroicon-o-eye class="size-4" />
                                            </x-button>
                                            @php
                                                $serviceForDashboard = $job->service ?? null;
                                                $dashboardBase = $serviceForDashboard
                                                    ? ($serviceForDashboard->public_url ?: $serviceForDashboard->base_url)
                                                    : null;
                                                $jobUuidForDashboard = $job->uuid ?? null;
                                                $horizonDashboardPath = \rtrim(\config('horizonhub.horizon_paths.dashboard'), '/');
                                                $horizonJobUrl = null;
                                                if ($dashboardBase && $jobUuidForDashboard) {
                                                    $horizonJobUrl = \rtrim($dashboardBase, '/') . $horizonDashboardPath . '/jobs/' . \urlencode((string) $jobUuidForDashboard);
                                                }
                                            @endphp
                                            @if($horizonJobUrl)
                                                <x-button
                                                    type="button"
                                                    variant="ghost"
                                                    class="h-8 min-h-8 px-2 rounded-md"
                                                    aria-label="Open in Horizon dashboard"
                                                    title="Open in Horizon dashboard"
                                                    onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
                                                >
                                                    <x-heroicon-o-window class="size-4" />
                                                </x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" data-column-id="service">
                                        <div class="empty-state">
                                            <x-heroicon-o-document-text class="empty-state-icon" />
                                            <p class="empty-state-title">No processing jobs</p>
                                            <p class="empty-state-description">Jobs currently being executed will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                </x-data-table>
                <div class="border-t border-border px-4 py-2 mt-2">
                    <x-pagination :paginator="$jobsProcessing" />
                </div>
                </div>
            </details>

            <details
                class="group border-b border-border pb-4"
                :open="sectionOpen.processed"
                @toggle="onToggle('processed', $event)"
            >
                <summary class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 text-section-title text-foreground [&::-webkit-details-marker]:hidden">
                    <x-heroicon-o-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
                    <span>Processed</span>
                </summary>
                <div class="pt-2">
                <x-data-table
                    resizable-key="horizon-job-list-processed"
                    column-ids="uuid,service,queue,job,attempts,queued_at,processed,failed_at,runtime,actions"
                    body-key="horizon-job-list-processed"
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                            <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                            <th class="table-header px-4 py-2.5" data-column-id="attempts">Attempts</th>
                            <th class="table-header px-4 py-2.5" data-column-id="queued_at">Queued at</th>
                            <th class="table-header px-4 py-2.5" data-column-id="processed">Processed</th>
                            <th class="table-header px-4 py-2.5" data-column-id="runtime">Runtime</th>
                            <th class="table-header px-4 py-2.5" data-column-id="actions">Actions</th>
                        </tr>
                    </x-slot:head>
                            @forelse($jobsProcessed as $job)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
                                        <a class="link" href="{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}">
                                            {{ $job->uuid }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate max-w-[180px]" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue">{{ $job->queue }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->uuid }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="attempts">
                                        @php $attempts = $job->attempts; $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–'; @endphp
                                        {{ $attemptsDisplay }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at" data-datetime="{{ $job->queued_at?->format('c') ?? '' }}">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="processed" data-datetime="{{ $job->processed_at?->format('c') ?? '' }}">{{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="runtime">{{ $job->runtime ?? '–' }}</td>
                                    <td class="px-4 py-2.5" data-column-id="actions">
                                        <div class="flex items-center gap-1">
                                            <x-button
                                                variant="secondary"
                                                class="h-8 min-h-8 p-2 rounded-md"
                                                aria-label="View"
                                                title="View"
                                                onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}'"
                                            >
                                                <x-heroicon-o-eye class="size-4" />
                                            </x-button>
                                            @php
                                                $serviceForDashboard = $job->service ?? null;
                                                $dashboardBase = $serviceForDashboard
                                                    ? ($serviceForDashboard->public_url ?: $serviceForDashboard->base_url)
                                                    : null;
                                                $jobUuidForDashboard = $job->uuid ?? null;
                                                $horizonDashboardPath = \rtrim(\config('horizonhub.horizon_paths.dashboard'), '/');
                                                $horizonJobUrl = null;
                                                if ($dashboardBase && $jobUuidForDashboard) {
                                                    $horizonJobUrl = \rtrim($dashboardBase, '/') . $horizonDashboardPath . '/jobs/' . \urlencode((string) $jobUuidForDashboard);
                                                }
                                            @endphp
                                            @if($horizonJobUrl)
                                                <x-button
                                                    type="button"
                                                    variant="ghost"
                                                    class="h-8 min-h-8 px-2 rounded-md"
                                                    aria-label="Open in Horizon dashboard"
                                                    title="Open in Horizon dashboard"
                                                    onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
                                                >
                                                    <x-heroicon-o-window class="size-4" />
                                                </x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" data-column-id="service">
                                        <div class="empty-state">
                                            <x-heroicon-o-document-text class="empty-state-icon" />
                                            <p class="empty-state-title">No processed jobs</p>
                                            <p class="empty-state-description">Completed jobs will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                </x-data-table>
                <div class="border-t border-border px-4 py-2 mt-2">
                    <x-pagination :paginator="$jobsProcessed" />
                </div>
                </div>
            </details>

            <details
                class="group border-b border-border pb-4"
                :open="sectionOpen.failed"
                @toggle="onToggle('failed', $event)"
            >
                <summary class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 text-section-title text-foreground [&::-webkit-details-marker]:hidden">
                    <x-heroicon-o-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
                    <span>Failed</span>
                </summary>
                <div class="pt-2">
                <x-data-table
                    resizable-key="horizon-job-list-failed"
                    column-ids="uuid,service,queue,job,attempts,queued_at,processed,failed_at,runtime,actions"
                    body-key="horizon-job-list-failed"
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                            <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                            <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                            <th class="table-header px-4 py-2.5" data-column-id="attempts">Attempts</th>
                            <th class="table-header px-4 py-2.5" data-column-id="queued_at">Queued at</th>
                            <th class="table-header px-4 py-2.5" data-column-id="failed_at">Failed at</th>
                            <th class="table-header px-4 py-2.5" data-column-id="runtime">Runtime</th>
                            <th class="table-header px-4 py-2.5" data-column-id="actions">Actions</th>
                        </tr>
                    </x-slot:head>
                            @forelse($jobsFailed as $job)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
                                        <a class="link" href="{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}">
                                            {{ $job->uuid }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate max-w-[180px]" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue">{{ $job->queue }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->uuid }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="attempts">
                                        @php $attempts = $job->attempts; $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–'; @endphp
                                        {{ $attemptsDisplay }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at" data-datetime="{{ $job->queued_at?->format('c') ?? '' }}">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="failed_at" data-datetime="{{ $job->failed_at?->format('c') ?? '' }}">{{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="runtime">{{ $job->runtime ?? '–' }}</td>
                                    <td class="px-4 py-2.5" data-column-id="actions">
                                        <div class="flex items-center gap-1">
                                            <x-button
                                                variant="secondary"
                                                class="h-8 min-h-8 p-2 rounded-md"
                                                aria-label="View"
                                                title="View"
                                                onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}'"
                                            >
                                                <x-heroicon-o-eye class="size-4" />
                                            </x-button>
                                            @php
                                                $serviceForDashboard = $job->service ?? null;
                                                $dashboardBase = $serviceForDashboard
                                                    ? ($serviceForDashboard->public_url ?: $serviceForDashboard->base_url)
                                                    : null;
                                                $jobUuidForDashboard = $job->uuid ?? null;
                                                $horizonDashboardPath = \rtrim(\config('horizonhub.horizon_paths.dashboard'), '/');
                                                $horizonJobUrl = null;
                                                if ($dashboardBase && $jobUuidForDashboard) {
                                                    $horizonJobUrl = \rtrim($dashboardBase, '/') . $horizonDashboardPath . '/jobs/' . \urlencode((string) $jobUuidForDashboard);
                                                }
                                            @endphp
                                            @if($horizonJobUrl)
                                                <x-button
                                                    type="button"
                                                    variant="ghost"
                                                    class="h-8 min-h-8 px-2 rounded-md"
                                                    aria-label="Open in Horizon dashboard"
                                                    title="Open in Horizon dashboard"
                                                    onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
                                                >
                                                    <x-heroicon-o-window class="size-4" />
                                                </x-button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" data-column-id="service">
                                        <div class="empty-state">
                                            <x-heroicon-o-document-text class="empty-state-icon" />
                                            <p class="empty-state-title">No failed jobs</p>
                                            <p class="empty-state-description">Failed jobs will appear here.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                </x-data-table>
                <div class="border-t border-border px-4 py-2 mt-2">
                    <x-pagination :paginator="$jobsFailed" />
                </div>
                </div>
            </details>
        </div>
        {{-- Retry jobs modal --}}
        <template x-if="retryModalMounted">
            <div>
                <x-confirm-modal
                    title="Retry jobs"
                    size="xl"
                    cancelText="Cancel"
                    :cancelAction="null"
                    :backdropAction="null"
                    x-data
                    x-show="showRetryModal"
                    x-on:close-modal.window="closeRetryModal()"
                >
                    <div
                        class="flex min-h-0 flex-1 flex-col overflow-hidden p-2"
                    >
                            <p class="text-sm text-muted-foreground mb-3">
                                Select failed jobs to retry. Filter by service, search or date range.
                            </p>
                        <div class="mb-3 flex shrink-0 flex-wrap items-end gap-3">
                            <div class="space-y-2">
                                <x-input-label for="retry-modal-service">Service</x-input-label>
                                <x-select
                                    id="retry-modal-service"
                                    class="w-44"
                                    x-model="retryFilters.service_id"
                                    @change="loadFailedJobs()"
                                >
                                    <option value="">All services</option>
                                    @foreach($services as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                                <div class="space-y-2">
                                    <x-input-label for="retry-modal-search">Search</x-input-label>
                                    <x-text-input
                                        id="retry-modal-search"
                                        type="text"
                                        placeholder="Queue, job or UUID"
                                        class="w-48"
                                        x-model="retryFilters.search"
                                        @change.debounce.300ms="loadFailedJobs()"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <x-input-label for="retry-modal-date-from">From</x-input-label>
                                    <x-input-date
                                        id="retry-modal-date-from"
                                        class="w-40"
                                        x-model="retryFilters.date_from"
                                        @change="loadFailedJobs()"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <x-input-label for="retry-modal-date-to">To</x-input-label>
                                    <x-input-date
                                        id="retry-modal-date-to"
                                        class="w-40"
                                        x-model="retryFilters.date_to"
                                        @change="loadFailedJobs()"
                                    />
                                </div>
                                <div class="flex items-end gap-2">
                                    <x-button
                                        type="button"
                                        variant="outline"
                                        class="h-9 text-sm"
                                        @click="selectAllFailed()"
                                    >
                                        Select all
                                    </x-button>
                                    <x-button
                                        type="button"
                                        variant="ghost"
                                        class="h-9 text-sm"
                                        @click="clearSelection()"
                                    >
                                        Clear selection
                                    </x-button>
                                </div>
                            </div>
                            <div
                                class="min-h-0 flex-1 overflow-x-auto overflow-y-auto rounded-md border border-border"
                                style="max-height: min(45vh, 320px);"
                            >
                                <x-data-table
                                    :wrap="false"
                                    table-class="text-sm"
                                    thead-class="bg-muted/50 sticky top-0"
                                >
                                    <x-slot:head>
                                        <tr class="border-b border-border">
                                            <th class="w-10 px-3 py-2 text-left"></th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Service</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Queue</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Job</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Failed at</th>
                                        </tr>
                                    </x-slot:head>
                                        <template x-if="failedJobs.length === 0">
                                            <tr>
                                                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                                    No failed jobs to show.
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-for="job in failedJobs" :key="job.uuid">
                                            <tr class="hover:bg-muted/30">
                                                <td class="px-3 py-2">
                                                    <template x-if="job.uuid">
                                                        <x-checkbox
                                                            x-bind:checked="selectedFailedIds.includes(job.uuid)"
                                                            @change="toggleFailed(job.uuid)"
                                                            x-bind:aria-label="'Select job ' + job.uuid"
                                                        />
                                                    </template>
                                                </td>
                                                <td class="px-3 py-2 text-muted-foreground" x-text="job.service_name || '–'"></td>
                                                <td class="px-3 py-2 font-mono text-xs text-muted-foreground" x-text="job.queue || '–'"></td>
                                                <td
                                                    class="px-3 py-2 text-muted-foreground truncate max-w-[200px]"
                                                    x-text="job.name || '–'"
                                                    x-bind:title="job.name || ''"
                                                ></td>
                                                <td
                                                    class="px-3 py-2 text-muted-foreground"
                                                    x-text="job.failed_at_formatted || '–'"
                                                    x-bind:data-datetime="job.failed_at_iso || ''"
                                                ></td>
                                            </tr>
                                        </template>
                                </x-data-table>
                            </div>
                            <div
                                class="flex items-center justify-between px-3 py-2 text-xs text-muted-foreground"
                                x-show="retryLastPage > 1"
                            >
                                <div x-text="'Page ' + retryPage + ' of ' + retryLastPage"></div>
                                <div class="space-x-2">
                                    <x-button
                                        type="button"
                                        variant="outline"
                                        class="h-7 px-2 text-xs"
                                        x-bind:disabled="retryPage <= 1"
                                        @click="prevRetryPage()"
                                    >
                                        Previous
                                    </x-button>
                                    <x-button
                                        type="button"
                                        variant="outline"
                                        class="h-7 px-2 text-xs"
                                        x-bind:disabled="retryPage >= retryLastPage"
                                        @click="nextRetryPage()"
                                    >
                                        Next
                                    </x-button>
                                </div>
                            </div>
                        </div>
                        <x-slot:footer>
                            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                                <x-button type="button" variant="ghost" @click="closeRetryModal()">Cancel</x-button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center justify-center gap-1 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50"
                                    x-bind:disabled="selectedFailedIds.length === 0 || retrying"
                                    @click="retrySelected()"
                                >
                                    <span x-show="!retrying" x-text="'Retry selected (' + selectedFailedIds.length + ')'"></span>
                                    <span x-show="retrying" class="inline-flex items-center gap-1">
                                        <x-loader class="size-4" />
                                        Retrying…
                                    </span>
                                </button>
                            </div>
                        </x-slot:footer>
                    </x-confirm-modal>
            </div>
        </template>

    </div>
@endsection
