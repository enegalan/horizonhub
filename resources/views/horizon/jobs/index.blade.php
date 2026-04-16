@extends('layouts.app')

@section('content')
    <div
        class="card"
        x-data="window.horizonJobsPage ? window.horizonJobsPage({
            failedListUrl: '{{ route('horizon.jobs.failed') }}',
            retryBatchUrl: '{{ route('horizon.jobs.retry-batch') }}',
            jobsPerPage: {{ \config('horizonhub.jobs_per_page') }},
        }) : {}"
    >
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <form method="GET" action="{{ route('horizon.index') }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="_top">
                <div class="space-y-2">
                    <x-input-label>Services</x-input-label>
                    <x-multiselect
                        name="serviceFilter"
                        class="w-56"
                        :selected="$filters['serviceIds'] ?? []"
                        placeholder="All services"
                    >
                        @foreach($services as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                        @endforeach
                    </x-multiselect>
                </div>
                <div class="space-y-2">
                    <x-input-label>Search</x-input-label>
                    <div class="flex items-center gap-2">
                        <x-text-input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Queue, job or UUID"
                            class="w-56"
                        />
                        <x-button type="submit" class="h-9 text-sm">
                            Search
                        </x-button>
                    </div>
                </div>
            </form>
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
            'columnIds' => 'uuid,service,queue,job,attempts,queued_at,delayed_until,processed,failed_at,runtime,actions',
            'resizablePrefix' => 'horizon-job-list',
        ])
        {{-- Retry jobs modal --}}
        <template x-if="retryModalMounted">
            <div>
                <x-confirm-modal
                    title="Retry jobs"
                    size="xxl"
                    x-data
                    x-show="showRetryModal"
                    x-on:close-modal.window="closeRetryModal()"
                >
                    <div
                        class="flex min-h-0 flex-1 flex-col overflow-hidden p-2"
                    >
                            <p class="text-sm text-muted-foreground mb-3">
                                Select failed jobs to retry. Choose services, search text, and failed-at range, then click <span class="font-medium text-foreground">Search</span> to load matching jobs.
                            </p>
                        <div class="mb-3 flex shrink-0 flex-wrap items-end gap-3">
                            <form
                                @submit.prevent="applyRetryModalFilters()"
                                class="contents"
                            >
                                <template x-for="session in [retryModalSession]" :key="session">
                                    <div class="space-y-2">
                                        <x-input-label>Services</x-input-label>
                                        <x-multiselect
                                            id="retry-modal-services"
                                            name="retryModalServiceFilter"
                                            class="w-56"
                                            :selected="[]"
                                            placeholder="All services"
                                            @change="retryFilters.service_ids = $event.detail.values"
                                        >
                                            @foreach($services as $s)
                                                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                                            @endforeach
                                        </x-multiselect>
                                    </div>
                                </template>
                                <div class="space-y-2">
                                    <x-input-label for="retry-modal-search">Search</x-input-label>
                                    <x-text-input
                                        id="retry-modal-search"
                                        name="retry_modal_search"
                                        type="text"
                                        placeholder="Queue, job or UUID"
                                        class="w-56"
                                        x-model="retryFilters.search"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="space-y-2 min-w-64 max-w-xl flex-1">
                                    <x-input-label for="retry-modal-failed-at-range">Failed at range</x-input-label>
                                    <x-input-date
                                        id="retry-modal-failed-at-range"
                                        :range="true"
                                        :with-time="true"
                                        x-model="retryFilters.failed_at_range"
                                    />
                                </div>
                                <x-button type="submit" class="h-9 text-sm shrink-0">
                                    Search
                                </x-button>
                            </form>
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
                                style="max-height: min(52vh, 520px);"
                            >
                                <x-table
                                    :wrap="false"
                                    resizable-key="horizon-retry-modal-failed-jobs"
                                    column-ids="select,service,queue,job,failed_at"
                                    body-key="horizon-retry-modal-failed-jobs"
                                    table-class="text-sm"
                                    thead-class="bg-muted/50 sticky top-0"
                                >
                                    <x-slot:head>
                                        <tr class="border-b border-border">
                                            <th class="table-header w-10 px-4 py-2.5" data-column-id="select"></th>
                                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="service">Service</th>
                                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                                            <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="failed_at">Failed at</th>
                                        </tr>
                                    </x-slot:head>
                                        <template x-if="failedJobs.length === 0">
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-center text-sm text-muted-foreground" data-column-id="service">
                                                    No failed jobs to show.
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-for="job in failedJobs" :key="job.uuid">
                                            <tr class="transition-colors hover:bg-muted/30">
                                                <td class="px-4 py-2.5" data-column-id="select">
                                                    <template x-if="job.uuid">
                                                        <x-checkbox
                                                            x-bind:checked="selectedFailedIds.includes(job.uuid)"
                                                            @change="toggleFailed(job.uuid)"
                                                            x-bind:aria-label="'Select job ' + job.uuid"
                                                        />
                                                    </template>
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="service" x-text="job.service_name || '–'"></td>
                                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue" x-text="job.queue || '–'"></td>
                                                <td
                                                    class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]"
                                                    data-column-id="job"
                                                    x-text="job.name || '–'"
                                                    x-bind:title="job.name || ''"
                                                ></td>
                                                <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="failed_at" x-text="job.failed_at_formatted || '–'"></td>
                                            </tr>
                                        </template>
                                </x-table>
                            </div>
                            <div class="px-4 py-2 mt-2" x-show="retryTotal > 0">
                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                    <p class="text-sm text-muted-foreground shrink-0" x-text="retryPaginationSummaryLine()"></p>
                                    <nav
                                        role="navigation"
                                        aria-label="Pagination"
                                        class="flex flex-wrap items-center gap-2"
                                        x-show="retryLastPage > 1"
                                    >
                                        <span
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground cursor-not-allowed"
                                            x-show="retryPage <= 1"
                                        >Previous</span>
                                        <a
                                            href="#"
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                            x-show="retryPage > 1"
                                            @click.prevent="prevRetryPage()"
                                        >Previous</a>

                                        <template x-for="(page, idx) in retryPaginationPages()" :key="idx + '-' + String(page)">
                                            <span class="contents">
                                                <span
                                                    class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground"
                                                    x-show="page === '...'"
                                                >...</span>
                                                <span
                                                    class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium bg-primary text-primary-foreground"
                                                    x-show="page !== '...' && Number(page) === retryPage"
                                                    aria-current="page"
                                                    x-text="page"
                                                ></span>
                                                <a
                                                    href="#"
                                                    class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                                    x-show="page !== '...' && Number(page) !== retryPage"
                                                    @click.prevent="setRetryPage(page)"
                                                    x-text="page"
                                                ></a>
                                            </span>
                                        </template>

                                        <span
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground cursor-not-allowed"
                                            x-show="retryPage >= retryLastPage"
                                        >Next</span>
                                        <a
                                            href="#"
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                                            x-show="retryPage < retryLastPage"
                                            @click.prevent="nextRetryPage()"
                                        >Next</a>
                                    </nav>
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
                                    <span x-cloak x-show="retrying" style="display: none" class="inline-flex items-center gap-1">
                                        <x-loader class="size-4" />
                                        Retrying...
                                    </span>
                                </button>
                            </div>
                        </x-slot:footer>
                    </x-confirm-modal>
            </div>
        </template>

    </div>
@endsection
