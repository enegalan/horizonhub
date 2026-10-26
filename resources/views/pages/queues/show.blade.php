@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-breadcrumbs :items="[
            ['label' => 'Queues', 'url' => route('horizon.queues.index')],
            ['label' => $queueName],
        ]" />

        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Queue"
                :title="$queueName"
                description="Aggregated workload and recent jobs for this queue across the selected Horizon services."
            >
                <x-slot:actions>
                    <a href="{{ route('horizon.queues.index') }}" class="link text-sm" data-turbo-action="replace">All queues</a>
                </x-slot:actions>
            </x-page-hero>

            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <form method="GET" action="{{ route('horizon.queues.show', ['queue' => $queueName]) }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="_top" data-service-tag-filter="1" data-service-tag-filter-manual="1">
                    <x-service-tag-filter
                        :all-tags="$allTags"
                        :selected-tags="$selectedTags"
                        :show-service-multiselect="true"
                        :services="$services"
                        :service-ids="$selectedServiceIds"
                        service-multiselect-id="queue-show-services"
                        service-multiselect-label="Services"
                    />
                    <div class="flex min-w-0 flex-1 flex-col gap-2 md:max-w-88">
                        <x-input-label for="queue-show-search">Search</x-input-label>
                        <div class="flex min-w-0 flex-col gap-2 md:flex-row md:items-center">
                            <x-text-input
                                id="queue-show-search"
                                type="text"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Job name or UUID"
                                class="w-full min-w-0 md:flex-1"
                            />
                            <x-button type="submit" class="h-9 w-full text-sm md:w-auto">
                                Filter
                            </x-button>
                        </div>
                    </div>
                </form>
            </div>

            <div id="queue-show-stats" class="grid gap-4 border-b border-border p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-5">
                @for ($i = 0; $i < 5; $i++)
                    <div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
                        <div class="skeleton h-3 w-20" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                        <div class="mt-2 skeleton h-7 w-16" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                    </div>
                @endfor
            </div>

            <div class="border-b border-border">
                <div class="flex items-center justify-between gap-2 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">Services</h3>
                    <p class="text-xs text-muted-foreground">Per-service breakdown</p>
                </div>
                <x-table
                    id="horizon-queue-services"
                    stream-patch-children
                >
                    <x-slot:head>
                        <x-table.th column="service" class="min-w-32">Service</x-table.th>
                        <x-table.th column="connection" class="min-w-24">Connection</x-table.th>
                        <x-table.th column="pending">Pending</x-table.th>
                        <x-table.th column="processes">Processes</x-table.th>
                        <x-table.th column="wait">Wait</x-table.th>
                        <x-table.th column="throughput">Throughput</x-table.th>
                    </x-slot:head>
                    <x-skeleton.table-rows rows="4" columns="6" />
                </x-table>
            </div>

            <div class="p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-section-title text-foreground">Metrics history</h3>
                    <p class="text-xs text-muted-foreground">Last 24 points</p>
                </div>
                <div id="queue-show-metrics">
                    <div class="grid gap-4 sm:grid-cols-3">
                        @for ($i = 0; $i < 3; $i++)
                            <div class="rounded-lg border border-border/70 bg-muted/10 px-4 py-3">
                                <div class="skeleton h-3 w-20" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                                <div class="mt-2 skeleton h-7 w-24" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                                <div class="mt-2 skeleton h-8 w-full" style="--skeleton-delay: {{ ($i * 60) + 140 }}ms"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/20 px-5 py-3 sm:px-6">
                <h3 class="text-section-title text-foreground">Jobs</h3>
                <p class="text-xs text-muted-foreground">Processing, processed and failed jobs in this queue</p>
            </div>

            @include('pages.jobs.partials.index.list-collapsible-stack', [
                'jobsProcessing' => $jobsProcessing,
                'jobsProcessed' => $jobsProcessed,
                'jobsFailed' => $jobsFailed,
                'showServiceColumn' => true,
                'pageService' => null,
                'resizablePrefix' => 'horizon-queue-show-jobs',
                'defer' => true,
            ])
        </div>
    </div>
@endsection
