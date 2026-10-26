@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-resource-index-page
            eyebrow="Batches"
            title="Batches"
            :description="'Recent batch jobs reported by each connected Horizon service. Horizon returns the latest ' . config('horizonhub.batches_recent_limit') . ' per service; search by name or ID to find older batches.'"
            :filter-action="route('horizon.batches.index')"
            filter-form-attributes='data-service-tag-filter="1" data-service-tag-filter-manual="1"'
            stats-target="horizon-batch-stats"
            :stats-columns="3"
            stats-view="pages.batches.partials.index.stats"
            :stats-view-data="[
                'activeCount' => $activeCount ?? 0,
                'batchCount' => $batchCount ?? 0,
                'failedCount' => $failedCount ?? 0,
                'mayBeTruncated' => $mayBeTruncated ?? false,
            ]"
            :defer="$defer ?? false"
        >
            <x-slot:filter>
                <x-service-tag-filter
                    :all-tags="$allTags ?? []"
                    :selected-tags="$selectedTags ?? []"
                    :show-service-multiselect="true"
                    :services="$services"
                    :service-ids="$selectedServiceIds ?? []"
                    service-multiselect-id="batches-index-services"
                    service-multiselect-label="Services"
                />
                <div class="flex min-w-0 flex-1 flex-col gap-2 md:max-w-88">
                    <x-input-label for="batches-index-search">Search</x-input-label>
                    <x-text-input
                        id="batches-index-search"
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Batch name or ID"
                        class="w-full min-w-0"
                    />
                </div>
            </x-slot:filter>

            <x-slot:divider>
                <div class="flex flex-wrap items-center justify-between gap-2 bg-muted/20 px-5 py-3 sm:px-6">
                    <h3 class="text-section-title text-foreground">Recent batches</h3>
                    <p class="text-xs text-muted-foreground">Latest {{ config('horizonhub.batches_recent_limit') }} per service</p>
                </div>
            </x-slot:divider>

            <x-slot:body>
                <x-table
                    id="horizon-batch-list"
                    stream-patch-children
                >
                    <x-slot:head>
                        <x-table.th column="service" class="min-w-30">Service</x-table.th>
                        <x-table.th column="batch" class="min-w-40">Batch</x-table.th>
                        <x-table.th column="status" class="min-w-24">Status</x-table.th>
                        <x-table.th column="size">Size</x-table.th>
                        <x-table.th column="completion">Completion</x-table.th>
                        <x-table.th column="created">Created</x-table.th>
                    </x-slot:head>
                    @include('pages.batches.partials.index.tbody', [
                        'batches' => $batches,
                        'defer' => $defer ?? false,
                        'search' => $search ?? '',
                    ])
                </x-table>
            </x-slot:body>
        </x-resource-index-page>
    </div>
@endsection
