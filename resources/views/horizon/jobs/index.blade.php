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
        @include('horizon.jobs.partials.job-list-collapsible-stack', [
            'jobsProcessing' => $jobsProcessing,
            'jobsProcessed' => $jobsProcessed,
            'jobsFailed' => $jobsFailed,
            'showServiceColumn' => true,
            'pageService' => null,
            'columnIds' => 'uuid,service,queue,job,attempts,queued_at,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-job-list',
        ])
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
