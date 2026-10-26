@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-resource-index-page
            eyebrow="Horizon"
            title="Instances"
            description="Masters and supervisors reported by each connected Horizon service. Filter by tag or service to focus on a subset."
            :filter-action="route('horizon.instances.index')"
            filter-form-attributes='data-service-tag-filter="1" data-service-tag-filter-manual="1"'
            stats-target="instances-stats"
            :stats-columns="4"
            stats-view="pages.instances.partials.index.stats"
            :stats-view-data="[
                'services' => 0,
                'masters' => 0,
                'supervisors' => 0,
                'processes' => 0,
            ]"
            :defer="$defer ?? false"
        >
            <x-slot:filter>
                <x-service-tag-filter
                    :all-tags="$allTags ?? []"
                    :selected-tags="$selectedTags ?? []"
                    :show-service-multiselect="true"
                    :services="$services ?? []"
                    :service-ids="$selectedServiceIds ?? []"
                    service-multiselect-id="instances-index-services"
                    service-multiselect-label="Services"
                />
            </x-slot:filter>

            <x-slot:body>
                <div id="instances-content" class="space-y-4">
                    @if(! empty($defer ?? false))
                        <div class="grid gap-4 xl:grid-cols-2">
                            @for ($i = 0; $i < 4; $i++)
                                <div class="card overflow-hidden p-4">
                                    <div class="skeleton h-4 w-40" style="--skeleton-delay: {{ $i * 60 }}ms"></div>
                                    <div class="mt-3 space-y-2">
                                        <div class="skeleton h-3 w-full" style="--skeleton-delay: {{ ($i * 60) + 80 }}ms"></div>
                                        <div class="skeleton h-3 w-4/5" style="--skeleton-delay: {{ ($i * 60) + 140 }}ms"></div>
                                    </div>
                                </div>
                            @endfor
                        </div>
                    @else
                        @include('pages.partials.master-groups', [
                            'emptyDescription' => 'No masters were reported by the connected Horizon services.',
                            'emptyTitle' => 'No instances',
                            'masterGroups' => [],
                            'showServiceLink' => true,
                        ])
                    @endif
                </div>
            </x-slot:body>
        </x-resource-index-page>
    </div>
@endsection
