@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-resource-index-page
            eyebrow="Workload"
            title="Queues"
            description="Pending jobs per queue across your Horizon services. Filter by tag or service to focus on a subset of workers."
            :filter-action="route('horizon.queues.index')"
            filter-form-attributes='data-service-tag-filter="1" data-service-tag-filter-manual="1"'
            stats-target="horizon-queue-stats"
            :stats-columns="2"
            stats-view="horizon.queues.partials.index.stats"
            :stats-view-data="['queueCount' => $queueCount ?? 0, 'totalJobs' => $totalJobs ?? 0]"
            :defer="$defer ?? false"
        >
            <x-slot:filter>
                <x-service-tag-filter
                    :all-tags="$allTags ?? []"
                    :selected-tags="$selectedTags ?? []"
                    :show-service-multiselect="true"
                    :services="$services"
                    :service-ids="$selectedServiceIds ?? []"
                    service-multiselect-id="queues-index-services"
                    service-multiselect-label="Services"
                />
            </x-slot:filter>

            <x-slot:divider>
                <div class="flex flex-wrap items-center justify-between gap-2 bg-muted/20 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">By queue</h3>
                    <a href="{{ route('horizon.metrics') }}" class="link text-xs" data-turbo-action="replace">Metrics</a>
                </div>
            </x-slot:divider>

            <x-slot:body>
                <x-table
                    id="horizon-queue-list"
                    stream-patch-children
                >
                    <x-slot:head>
                        <x-table.th column="service" class="min-w-[120px]">Service</x-table.th>
                        <x-table.th column="queue" class="min-w-[100px]">Queue</x-table.th>
                        <x-table.th column="job_count">Pending jobs</x-table.th>
                    </x-slot:head>
                    @include('horizon.queues.partials.index.tbody', [
                        'queues' => $queues,
                        'defer' => $defer ?? false,
                    ])
                </x-table>
            </x-slot:body>
        </x-resource-index-page>
    </div>
@endsection
