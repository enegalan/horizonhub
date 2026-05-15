@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.horizonJobsPage ? window.horizonJobsPage({
            failedListUrl: '{{ route('horizon.jobs.failed') }}',
            retryBatchUrl: '{{ route('horizon.jobs.retry-batch') }}',
            jobsPerPage: {{ config('horizonhub.jobs_per_page') }},
        }) : {}"
    >
        <div class="card overflow-hidden">
            <div class="relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card px-5 py-5 sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Job monitor</p>
                        <h2 class="text-section-title text-foreground">Queues &amp; workloads</h2>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Inspect processing, completed, and failed jobs across services. Filter by service or search, and retry failures in bulk when needed.
                        </p>
                    </div>
                </div>
            </div>

            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <form method="GET" action="{{ route('horizon.jobs.index') }}" class="flex min-w-0 flex-1 flex-col flex-wrap gap-3 sm:flex-row sm:items-end" data-turbo-frame="_top">
                        <div class="min-w-0 space-y-2 sm:max-w-none">
                            <x-input-label id="jobs-index-services-label" for="jobs-index-services">Services</x-input-label>
                            <x-multiselect
                                id="jobs-index-services"
                                labelled-by="jobs-index-services-label"
                                name="serviceFilter"
                                class="w-full min-w-0 sm:w-56"
                                :selected="$filters['serviceIds'] ?? []"
                                placeholder="All services"
                                empty-message="No services found"
                            >
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                                @endforeach
                            </x-multiselect>
                        </div>
                        <div class="min-w-0 flex-1 space-y-2 sm:max-w-none">
                            <x-input-label for="jobs-index-search">Search</x-input-label>
                            <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
                                <x-text-input
                                    id="jobs-index-search"
                                    type="text"
                                    name="search"
                                    value="{{ $filters['search'] ?? '' }}"
                                    placeholder="Queue, job or UUID"
                                    class="w-full min-w-0 sm:w-56"
                                />
                                <x-button type="submit" class="h-9 text-sm">
                                    Search
                                </x-button>
                            </div>
                        </div>
                    </form>
                    <div class="flex shrink-0 items-end gap-2">
                        <x-button type="button" variant="secondary" class="h-9 text-sm" @click="openRetryModal()">
                            <x-heroicon-o-arrow-path class="size-4" />
                            Retry failed jobs
                        </x-button>
                    </div>
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
                'defer' => $defer ?? false,
            ])
        </div>

        <template x-if="retryModalMounted">
            <div>
                <x-confirm-modal
                    title="Retry jobs"
                    size="xxl"
                    x-data
                    x-show="showRetryModal"
                    x-on:close-modal.window="closeRetryModal()"
                >
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden p-2">
                        <p class="mb-3 text-sm text-muted-foreground">
                            Select failed jobs to retry. Choose services, search text, and failed-at range, then click <span class="font-medium text-foreground">Search</span> to load matching jobs.
                        </p>
                        <div class="mb-3 flex shrink-0 flex-wrap items-end gap-3">
                            <form
                                @submit.prevent="applyRetryModalFilters()"
                                class="contents"
                            >
                                <template x-for="session in [retryModalSession]" :key="session">
                                    <div class="min-w-0 space-y-2">
                                        <x-input-label id="retry-modal-services-label" for="retry-modal-services">Services</x-input-label>
                                        <x-multiselect
                                            id="retry-modal-services"
                                            labelled-by="retry-modal-services-label"
                                            name="retryModalServiceFilter"
                                            class="w-full min-w-0 sm:w-56"
                                            :selected="[]"
                                            placeholder="All services"
                                            empty-message="No services found"
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
                                        class="w-full min-w-0 sm:w-56"
                                        x-model="retryFilters.search"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="min-w-0 w-full max-w-xl flex-1 space-y-2 sm:min-w-64">
                                    <x-input-label for="retry-modal-failed-at-range">Failed at range</x-input-label>
                                    <x-input-date
                                        id="retry-modal-failed-at-range"
                                        :range="true"
                                        :with-time="true"
                                        x-model="retryFilters.failed_at_range"
                                    />
                                </div>
                                <x-button type="submit" class="h-9 shrink-0 text-sm">
                                    Search
                                </x-button>
                            </form>
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
                                thead-class="sticky top-0 bg-muted/50"
                            >
                                <x-slot:head>
                                    <tr class="border-b border-border">
                                        <th
                                            class="table-header w-10 cursor-pointer px-4 py-2.5"
                                            data-column-id="select"
                                            data-column-fixed
                                            @click="toggleAllFailedSelection()"
                                        >
                                            <div class="pointer-events-none flex justify-center">
                                                <x-checkbox
                                                    x-bind:checked="selectedFailedJobs.length > 0"
                                                    aria-label="Select all failed jobs"
                                                />
                                            </div>
                                        </th>
                                        <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="service">Service</th>
                                        <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="queue">Queue</th>
                                        <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                                        <th class="table-header min-w-[100px] px-4 py-2.5" data-column-id="failed_at">Failed at</th>
                                    </tr>
                                </x-slot:head>
                                <template x-if="failedJobs.length === 0">
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-sm text-muted-foreground" data-column-id="service">
                                            No failed jobs to show.
                                        </td>
                                    </tr>
                                </template>
                                <template x-for="(job, index) in failedJobs" :key="job.uuid">
                                    <tr class="transition-colors hover:bg-muted/30">
                                        <td
                                            class="px-4 py-2.5"
                                            data-column-id="select"
                                            x-bind:class="job.uuid ? 'cursor-pointer' : ''"
                                            @click="job.uuid ? toggleFailed(job.uuid, job.service_id, index, $event) : null"
                                        >
                                            <template x-if="job.uuid">
                                                <div class="pointer-events-none">
                                                    <x-checkbox
                                                        x-bind:checked="isFailedJobSelected(job.uuid)"
                                                        x-bind:aria-label="'Select job ' + job.uuid"
                                                    />
                                                </div>
                                            </template>
                                        </td>
                                        <td class="max-w-[180px] truncate px-4 py-2.5 text-sm text-muted-foreground" data-column-id="service" x-text="job.service_name || '–'"></td>
                                        <td class="max-w-[180px] truncate px-4 py-2.5 font-mono text-xs text-muted-foreground" data-column-id="queue" x-text="job.queue || '–'"></td>
                                        <td
                                            class="max-w-[180px] truncate px-4 py-2.5 text-sm text-muted-foreground"
                                            data-column-id="job"
                                            x-text="job.name || '–'"
                                            x-bind:title="job.name || ''"
                                        ></td>
                                        <td class="max-w-[180px] truncate px-4 py-2.5 text-xs text-muted-foreground" data-column-id="failed_at" x-text="job.failed_at_formatted || '–'"></td>
                                    </tr>
                                </template>
                            </x-table>
                        </div>
                        <div class="mt-2 px-4 py-2" x-show="retryTotal > 0">
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                <p class="shrink-0 text-sm text-muted-foreground" x-text="retryPaginationSummaryLine()"></p>
                                <nav
                                    role="navigation"
                                    aria-label="Pagination"
                                    class="flex flex-wrap items-center gap-2"
                                    x-show="retryLastPage > 1"
                                >
                                    <span
                                        class="inline-flex cursor-not-allowed items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground"
                                        x-show="retryPage <= 1"
                                    >Previous</span>
                                    <a
                                        href="#"
                                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
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
                                                class="inline-flex items-center justify-center rounded-md bg-primary px-2 py-1 text-sm font-medium text-primary-foreground"
                                                x-show="page !== '...' && Number(page) === retryPage"
                                                aria-current="page"
                                                x-text="page"
                                            ></span>
                                            <a
                                                href="#"
                                                class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                                x-show="page !== '...' && Number(page) !== retryPage"
                                                @click.prevent="setRetryPage(page)"
                                                x-text="page"
                                            ></a>
                                        </span>
                                    </template>

                                    <span
                                        class="inline-flex cursor-not-allowed items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground"
                                        x-show="retryPage >= retryLastPage"
                                    >Next</span>
                                    <a
                                        href="#"
                                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
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
                                x-bind:disabled="selectedFailedJobs.length === 0 || retrying"
                                @click="retrySelected()"
                            >
                                <span x-show="!retrying" x-text="'Retry selected (' + selectedFailedJobs.length + ')'"></span>
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
