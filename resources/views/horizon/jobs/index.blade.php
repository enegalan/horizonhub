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
            <x-page-hero
                eyebrow="Job monitor"
                title="Queues & workloads"
                description="Inspect processing, completed, and failed jobs across services. Filter by service or search, and retry failures in bulk when needed."
            />

            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between">
                    <form method="GET" action="{{ route('horizon.jobs.index') }}" class="flex min-w-0 flex-1 flex-col flex-wrap gap-3 sm:flex-row sm:items-end" data-turbo-frame="_top" data-service-tag-filter="1">
                        <x-service-tag-filter
                            :all-tags="$allTags ?? []"
                            :selected-tags="$selectedTags ?? []"
                            :show-service-multiselect="true"
                            :services="$services"
                            :service-ids="$filters['serviceIds'] ?? []"
                            service-multiselect-id="jobs-index-services"
                            service-multiselect-name="serviceFilter"
                            service-multiselect-label="Services"
                        />
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
                    size="xxl"
                    x-data
                    x-show="showRetryModal"
                    x-on:close-modal.window="closeRetryModal()"
                >
                    <x-slot:header>
                        <div class="relative -mx-3 -mt-3 shrink-0 overflow-hidden border-b border-border bg-gradient-to-br from-primary/15 via-card to-card px-3 py-3 sm:-mx-4 sm:-mt-4 sm:px-6 sm:py-5">
                            <div class="pointer-events-none absolute -right-10 -top-10 size-36 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                            <div class="relative flex items-start justify-between gap-2 sm:gap-3">
                                <div class="flex min-w-0 items-start gap-2.5 sm:gap-4">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary shadow-sm sm:size-11">
                                        <x-heroicon-o-arrow-path class="size-4 sm:size-6" />
                                    </div>
                                    <div class="min-w-0 space-y-0.5 sm:space-y-1">
                                        <h2 class="text-lg font-semibold leading-tight text-foreground sm:text-section-title">Retry failed jobs</h2>
                                        <p class="hidden max-w-2xl text-sm text-muted-foreground sm:block">
                                            Filter by service, search, or failure time. Select jobs below, then retry in bulk.
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="btn-ghost -mr-1 inline-flex size-9 shrink-0 items-center justify-center rounded-md p-0"
                                    aria-label="Close"
                                    @click="closeRetryModal()"
                                >
                                    <x-heroicon-o-x-mark class="size-5" />
                                </button>
                            </div>
                        </div>
                    </x-slot:header>

                    <div class="flex min-w-0 flex-col gap-3 sm:gap-4">
                        <section class="shrink-0 rounded-lg border border-border bg-muted/20 p-3 sm:p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <x-heroicon-o-funnel class="size-4 text-primary" />
                                <h3 class="text-sm font-medium text-foreground">Filters</h3>
                            </div>
                            <form
                                @submit.prevent="applyRetryModalFilters()"
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto] lg:items-end"
                            >
                                <template x-for="session in [retryModalSession]" :key="session">
                                    <div class="min-w-0 space-y-2">
                                        <x-input-label id="retry-modal-services-label" for="retry-modal-services">Services</x-input-label>
                                        <x-multiselect
                                            id="retry-modal-services"
                                            labelled-by="retry-modal-services-label"
                                            name="retryModalServiceFilter"
                                            class="w-full min-w-0"
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
                                <div class="min-w-0 space-y-2">
                                    <x-input-label for="retry-modal-search">Search</x-input-label>
                                    <x-text-input
                                        id="retry-modal-search"
                                        name="retry_modal_search"
                                        type="text"
                                        placeholder="Queue, job or UUID"
                                        class="w-full min-w-0"
                                        x-model="retryFilters.search"
                                        autocomplete="off"
                                    />
                                </div>
                                <div class="min-w-0 space-y-2 sm:col-span-2 lg:col-span-1">
                                    <x-input-label for="retry-modal-failed-at-range">Failed at range</x-input-label>
                                    <x-input-date
                                        id="retry-modal-failed-at-range"
                                        :range="true"
                                        :with-time="true"
                                        x-model="retryFilters.failed_at_range"
                                    />
                                </div>
                                <div class="flex items-end sm:col-span-2 sm:justify-end lg:col-span-1 lg:justify-start">
                                    <x-button type="submit" class="h-9 w-full shrink-0 whitespace-nowrap sm:w-auto">
                                        <x-heroicon-o-magnifying-glass class="size-4 shrink-0" />
                                        Search
                                    </x-button>
                                </div>
                            </form>
                        </section>

                        <section class="rounded-lg border border-border bg-card">
                            <div class="flex shrink-0 flex-col gap-2 border-b border-border bg-muted/25 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                                <div class="flex min-w-0 items-center gap-2">
                                    <x-heroicon-o-exclamation-triangle class="size-4 shrink-0 text-destructive" />
                                    <h3 class="text-sm font-medium text-foreground">Matching failed jobs</h3>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs sm:justify-end">
                                    <span
                                        class="inline-flex items-center rounded-full border border-border bg-background px-2.5 py-0.5 font-medium text-muted-foreground"
                                        x-show="retryTotal > 0"
                                        x-text="retryTotal + ' total'"
                                    ></span>
                                    <span
                                        class="inline-flex items-center rounded-full border border-primary/30 bg-primary/10 px-2.5 py-0.5 font-medium text-primary"
                                        x-show="selectedFailedJobs.length > 0"
                                        x-text="selectedFailedJobs.length + ' selected'"
                                    ></span>
                                    <button
                                        type="button"
                                        class="link text-xs"
                                        x-show="selectedFailedJobs.length > 0"
                                        @click="clearSelection()"
                                    >
                                        Clear selection
                                    </button>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        x-show="selectingAllFailed"
                                    >
                                        Selecting all…
                                    </span>
                                </div>
                            </div>

                            <div class="relative">
                                <div
                                    class="absolute inset-0 z-10 flex items-center justify-center bg-card/80 backdrop-blur-[1px]"
                                    x-show="retryLoadingJobs"
                                    x-cloak
                                >
                                    <x-loader class="size-6 text-primary" />
                                </div>

                                <div class="overflow-x-auto overscroll-x-contain">
                                <x-table
                                    :wrap="false"
                                    resizable-key="horizon-retry-modal-failed-jobs"
                                    column-ids="select,service,queue,job,failed_at"
                                    body-key="horizon-retry-modal-failed-jobs"
                                    table-class="text-sm"
                                    thead-class="sticky top-0 z-[1] bg-muted/80 backdrop-blur-sm"
                                >
                                    <x-slot:head>
                                        <tr class="border-b border-border">
                                            <th
                                                class="table-header w-10 cursor-pointer px-3 py-2.5 sm:px-4"
                                                data-column-id="select"
                                                data-column-fixed
                                                @click="toggleAllFailedSelection()"
                                            >
                                                <div class="pointer-events-none flex justify-center">
                                                    <x-checkbox
                                                        x-bind:checked="selectedFailedJobs.length > 0"
                                                        aria-label="Select all failed jobs matching filters"
                                                    />
                                                </div>
                                            </th>
                                            <th class="table-header hidden min-w-[88px] px-3 py-2.5 sm:table-cell sm:min-w-[100px] sm:px-4" data-column-id="service">Service</th>
                                            <th class="table-header hidden min-w-[72px] px-3 py-2.5 md:table-cell md:min-w-[100px] md:px-4" data-column-id="queue">Queue</th>
                                            <th class="table-header min-w-[120px] px-3 py-2.5 sm:px-4" data-column-id="job">Job</th>
                                            <th class="table-header min-w-[96px] whitespace-nowrap px-3 py-2.5 sm:min-w-[100px] sm:px-4" data-column-id="failed_at">Failed at</th>
                                        </tr>
                                    </x-slot:head>
                                    <template x-if="!retryLoadingJobs && failedJobs.length === 0">
                                        <tr>
                                            <td colspan="5" class="p-0" data-column-id="service">
                                                <x-empty-state
                                                    title="No failed jobs to show"
                                                    description="Adjust filters and run Search, or there are no failures in this range."
                                                    class="py-10 sm:py-12"
                                                >
                                                    <x-slot:icon>
                                                        <x-heroicon-o-inbox class="empty-state-icon mx-auto" />
                                                    </x-slot:icon>
                                                </x-empty-state>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-for="(job, index) in failedJobs" :key="job.uuid">
                                        <tr
                                            class="transition-colors hover:bg-muted/30"
                                            x-bind:class="isFailedJobSelected(job.uuid) ? 'bg-primary/5' : ''"
                                        >
                                            <td
                                                class="px-3 py-2.5 sm:px-4"
                                                data-column-id="select"
                                                x-bind:class="job.uuid ? 'cursor-pointer' : ''"
                                                @click="job.uuid ? toggleFailed(job.uuid, job.service_id, index, $event) : null"
                                            >
                                                <template x-if="job.uuid">
                                                    <div class="pointer-events-none flex justify-center sm:justify-start">
                                                        <x-checkbox
                                                            x-bind:checked="isFailedJobSelected(job.uuid)"
                                                            x-bind:aria-label="'Select job ' + job.uuid"
                                                        />
                                                    </div>
                                                </template>
                                            </td>
                                            <td class="hidden max-w-[8rem] truncate px-3 py-2.5 text-sm text-muted-foreground sm:table-cell sm:max-w-[11rem] sm:px-4" data-column-id="service" x-text="job.service_name || '–'"></td>
                                            <td class="hidden max-w-[6rem] truncate px-3 py-2.5 font-mono text-xs text-muted-foreground md:table-cell md:max-w-[11rem] md:px-4" data-column-id="queue" x-text="job.queue || '–'"></td>
                                            <td
                                                class="max-w-[10rem] px-3 py-2.5 sm:max-w-[14rem] sm:px-4"
                                                data-column-id="job"
                                                x-bind:title="(job.name || '') + (job.service_name ? ' · ' + job.service_name : '')"
                                            >
                                                <div class="flex min-w-0 flex-col gap-0.5">
                                                    <span class="truncate text-sm text-foreground" x-text="job.name || '–'"></span>
                                                    <span
                                                        class="truncate text-xs text-muted-foreground sm:hidden"
                                                        x-show="job.service_name"
                                                        x-text="job.service_name"
                                                    ></span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-2.5 text-xs text-muted-foreground sm:px-4" data-column-id="failed_at" x-text="job.failed_at_formatted || '–'"></td>
                                        </tr>
                                    </template>
                                </x-table>
                                </div>
                            </div>

                            <div class="border-t border-border bg-muted/15 px-3 py-2 sm:px-4" x-show="retryTotal > 0 && !retryLoadingJobs">
                                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                    <p class="shrink-0 text-sm text-muted-foreground" x-text="retryPaginationSummaryLine()"></p>
                                    <nav
                                        role="navigation"
                                        aria-label="Retry modal pagination"
                                        class="flex flex-wrap items-center gap-1 sm:gap-2"
                                        x-show="retryLastPage > 1"
                                    >
                                        <span
                                            class="inline-flex cursor-not-allowed items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground"
                                            x-show="retryPage <= 1"
                                        >Previous</span>
                                        <a
                                            href="#"
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            x-show="retryPage > 1"
                                            @click.prevent="prevRetryPage()"
                                        >Previous</a>

                                        <template x-for="(page, idx) in retryPaginationPages()" :key="idx + '-' + String(page)">
                                            <span class="contents">
                                                <span
                                                    class="inline-flex min-w-[2rem] items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground"
                                                    x-show="page === '...'"
                                                >...</span>
                                                <span
                                                    class="inline-flex min-w-[2rem] items-center justify-center rounded-md bg-primary px-2 py-1 text-sm font-medium text-primary-foreground"
                                                    x-show="page !== '...' && Number(page) === retryPage"
                                                    aria-current="page"
                                                    x-text="page"
                                                ></span>
                                                <a
                                                    href="#"
                                                    class="inline-flex min-w-[2rem] items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
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
                                            class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            x-show="retryPage < retryLastPage"
                                            @click.prevent="nextRetryPage()"
                                        >Next</a>
                                    </nav>
                                </div>
                            </div>
                        </section>
                    </div>

                    <x-slot:footer>
                        <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="hidden text-xs text-muted-foreground sm:block sm:text-left">
                                Tip: Shift+click to select a range across pages.
                            </p>
                            <div class="flex w-full min-w-0 flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:justify-end">
                                <x-button type="button" variant="ghost" class="w-full sm:w-auto" @click="closeRetryModal()">
                                    Cancel
                                </x-button>
                                <x-button
                                    type="button"
                                    class="w-full gap-1.5 sm:w-auto"
                                    x-bind:disabled="selectedFailedJobs.length === 0 || retrying"
                                    @click="retrySelected()"
                                >
                                    <span x-show="!retrying" class="inline-flex items-center justify-center gap-1.5">
                                        <x-heroicon-o-arrow-path class="size-4 shrink-0" />
                                        <span class="sm:hidden" x-text="'Retry (' + selectedFailedJobs.length + ')'"></span>
                                        <span class="hidden sm:inline" x-text="'Retry selected (' + selectedFailedJobs.length + ')'"></span>
                                    </span>
                                    <span x-cloak x-show="retrying" style="display: none" class="inline-flex items-center gap-1.5">
                                        <x-loader class="size-4" />
                                        Retrying…
                                    </span>
                                </x-button>
                            </div>
                        </div>
                    </x-slot:footer>
                </x-confirm-modal>
            </div>
        </template>
    </div>
@endsection
