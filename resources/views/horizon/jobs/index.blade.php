@extends('layouts.app')

@section('content')
    <div
        class="card"
        x-data="window.horizonJobsPage ? window.horizonJobsPage({
            failedListUrl: '{{ route('horizon.jobs.failed') }}',
            retryBatchUrl: '{{ route('horizon.jobs.retry-batch') }}',
            cleanUrl: '{{ route('horizon.jobs.clean') }}',
            jobsPerPage: {{ config('horizonhub.jobs_per_page') }},
        }) : {}"
        x-init="
            if (typeof cleanFilters !== 'undefined') {
                cleanFilters.service_id = '{{ $filters['serviceFilter'] ?? '' }}';
                cleanFilters.status = '{{ $filters['statusFilter'] ?? '' }}';
            }
            if (typeof init === 'function') { init(); }
        "
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
                    <input type="hidden" name="statusFilter" value="{{ $filters['statusFilter'] ?? '' }}">
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                </form>
            </div>
            <div class="space-y-2">
                <x-input-label>Status</x-input-label>
                <form method="GET" action="{{ route('horizon.index') }}">
                    <x-select name="statusFilter" class="w-32" onchange="this.form.submit()" placeholder="All">
                        <option value="processed" @selected(($filters['statusFilter'] ?? '') === 'processed')>Processed</option>
                        <option value="failed" @selected(($filters['statusFilter'] ?? '') === 'failed')>Failed</option>
                        <option value="processing" @selected(($filters['statusFilter'] ?? '') === 'processing')>Processing</option>
                    </x-select>
                    <input type="hidden" name="serviceFilter" value="{{ $filters['serviceFilter'] ?? '' }}">
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
                    <input type="hidden" name="statusFilter" value="{{ $filters['statusFilter'] ?? '' }}">
                </form>
            </div>
            <div class="flex items-end gap-2 ml-auto">
                <x-button type="button" variant="outline" class="h-9 text-sm" @click="openCleanModal()">
                    Clean jobs
                </x-button>
                <x-button type="button" class="h-9 text-sm" @click="openRetryModal()">
                    Retry failed jobs
                </x-button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-job-list" data-column-ids="service,queue,job,status,attempts,queued_at,processed,failed_at,runtime,actions">
                <thead>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="attempts">Attempts</th>
                        <th class="table-header px-4 py-2.5" data-column-id="queued_at">Queued at</th>
                        <th class="table-header px-4 py-2.5" data-column-id="processed">Processed</th>
                        <th class="table-header px-4 py-2.5" data-column-id="failed_at">Failed at</th>
                        <th class="table-header px-4 py-2.5" data-column-id="runtime">Runtime</th>
                        <th class="table-header px-4 py-2.5" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($jobs as $job)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground" data-column-id="queue">{{ $job->queue }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->job_uuid }}</td>
                            <td class="px-4 py-2.5" data-column-id="status">
                                @php $jobStatus = $job->status ?? '–'; @endphp
                                @if($jobStatus === 'failed')
                                    <span class="badge-danger">{{ $jobStatus }}</span>
                                @elseif($jobStatus === 'processed')
                                    <span class="badge-success">{{ $jobStatus }}</span>
                                @else
                                    <span class="badge-muted">{{ $jobStatus }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="attempts">
                                @php
                                    $attempts = $job->attempts;
                                    $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–';
                                @endphp
                                {{ $attemptsDisplay }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-xs text-muted-foreground"
                                data-column-id="queued_at"
                                data-datetime="{{ $job->queued_at?->toIso8601String() ?? '' }}"
                            >
                                {{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-xs text-muted-foreground"
                                data-column-id="processed"
                                data-datetime="{{ $job->processed_at?->toIso8601String() ?? '' }}"
                            >
                                {{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}
                            </td>
                            <td
                                class="px-4 py-2.5 text-xs text-muted-foreground"
                                data-column-id="failed_at"
                                data-datetime="{{ $job->failed_at?->toIso8601String() ?? '' }}"
                            >
                                {{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="runtime">
                                {{ $job->getFormattedRuntime() ?? '–' }}
                            </td>
                            <td class="px-4 py-2.5" data-column-id="actions">
                                <div class="flex items-center gap-2">
                                    <x-button
                                        variant="secondary"
                                        class="h-8 min-h-8 p-2 rounded-md"
                                        aria-label="View"
                                        title="View"
                                        onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->id]) }}'"
                                    >
                                        <x-heroicon-o-eye class="size-4" />
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" data-column-id="service">
                                <div class="empty-state">
                                    <x-heroicon-o-document-text class="empty-state-icon" />
                                    <p class="empty-state-title">No jobs yet</p>
                                    @if($services->isEmpty())
                                        <p class="empty-state-description">Register a service and push events from the Agent to see jobs here.</p>
                                        <x-button type="button" class="text-xs" onclick="window.location.href='{{ route('horizon.services.index') }}'">Register a service</x-button>
                                    @else
                                        <p class="empty-state-description">No jobs match the current filters.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-2">
            <x-pagination :paginator="$jobs" />
        </div>
        {{-- Retry jobs modal --}}
        <template x-if="showRetryModal">
            <div>
                <x-confirm-modal
                    title="Retry jobs"
                    size="xl"
                    cancelText="Cancel"
                    :cancelAction="null"
                    :backdropAction="null"
                    x-data
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
                                <table class="min-w-full text-sm">
                                    <thead class="bg-muted/50 sticky top-0">
                                        <tr class="border-b border-border">
                                            <th class="w-10 px-3 py-2 text-left"></th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Service</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Queue</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Job</th>
                                            <th class="px-3 py-2 text-left font-medium text-foreground">Failed at</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <template x-if="failedJobs.length === 0">
                                            <tr>
                                                <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">
                                                    No failed jobs to show.
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-for="job in failedJobs" :key="job.id">
                                            <tr class="hover:bg-muted/30">
                                                <td class="px-3 py-2">
                                                    <template x-if="job.has_service">
                                                        <x-checkbox
                                                            x-bind:checked="selectedFailedIds.includes(job.id)"
                                                            @change="toggleFailed(job.id)"
                                                            x-bind:aria-label="'Select job ' + job.id"
                                                        />
                                                    </template>
                                                    <template x-if="!job.has_service">
                                                        <span class="text-muted-foreground" title="No service">–</span>
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
                                    </tbody>
                                </table>
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

        {{-- Clean jobs modal --}}
        <template x-if="showCleanModal">
            <div>
                <x-confirm-modal
                    title="Clean jobs"
                    message=""
                    variant="warning"
                    backdropVariant="default"
                    size="md"
                    confirmText="Delete"
                    :confirmAction="null"
                    :cancelAction="null"
                    :backdropAction="null"
                    x-data
                    x-on:close-modal.window="closeCleanModal()"
                >
                    <template x-if="cleanStep === 1">
                        <div>
                            <p class="text-xs text-muted-foreground mb-3">
                                Choose filters. Matching jobs will be permanently deleted.
                            </p>
                            <div class="space-y-2">
                                <div class="space-y-2">
                                    <x-input-label>Service</x-input-label>
                                    <x-select
                                        class="w-full"
                                        x-model="cleanFilters.service_id"
                                        @change="updateCleanCount()"
                                    >
                                        <option value="">All</option>
                                        @foreach($services as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </x-select>
                                </div>
                                <div class="space-y-2">
                                    <x-input-label>Status</x-input-label>
                                    <x-select
                                        class="w-full"
                                        x-model="cleanFilters.status"
                                        @change="updateCleanCount()"
                                    >
                                        <option value="">All</option>
                                        <option value="processed">Processed</option>
                                        <option value="failed">Failed</option>
                                        <option value="processing">Processing</option>
                                    </x-select>
                                </div>
                                <div class="space-y-2">
                                    <x-input-label>Job type</x-input-label>
                                    <x-text-input
                                        type="text"
                                        placeholder="e.g. App\Jobs\SendEmail"
                                        class="w-full"
                                        x-model="cleanFilters.job_type"
                                        @change.debounce.300ms="updateCleanCount()"
                                    />
                                </div>
                                <p class="text-sm text-muted-foreground" x-text="cleanCount + ' job(s) match.'"></p>
                            </div>
                        </div>
                    </template>
                    <x-slot:footer>
                        <div class="flex w-full flex-wrap items-center justify-end gap-2">
                            <x-button type="button" variant="ghost" @click="closeCleanModal()">Cancel</x-button>
                            <x-button
                                type="button"
                                x-show="cleanStep === 1"
                                x-bind:disabled="cleanCount === 0 || cleaning"
                                @click="confirmClean()"
                            >
                                Next
                            </x-button>
                            <x-button
                                type="button"
                                variant="destructive"
                                x-show="cleanStep === 2"
                                x-bind:disabled="cleaning"
                                @click="runClean()"
                            >
                                <span x-show="!cleaning">Delete</span>
                                <span x-show="cleaning" class="inline-flex items-center gap-1">
                                    <x-loader class="size-4" />
                                    Deleting…
                                </span>
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-confirm-modal>
            </div>
        </template>
    </div>
@endsection
