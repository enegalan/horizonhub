@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.deleteConfirm ? window.deleteConfirm('Service') : {}"
    >
        <x-resource-index-page
            eyebrow="Connected Horizon instances"
            title="Services"
            description="Register each Horizon deployment, monitor its health, and open its dashboard when you need to inspect queues and workers."
            list-component="window.horizonServicesList ? window.horizonServicesList() : {}"
            :filter-action="route('horizon.services.index')"
            filter-form-attributes='data-service-tag-filter="1" data-service-tag-filter-manual="1"'
            stats-target="horizon-service-stats"
            stats-view="horizon.services.partials.index.stats"
            body-target="tbody-horizon-service-list"
            body-view="horizon.services.partials.index.tbody"
            :body-view-data="['services' => $services]"
            :defer="$defer ?? false"
        >
            <x-slot:actions>
                <x-form-drawer-link :href="route('horizon.services.create')" class="h-9 shrink-0 text-sm">
                    Register service
                </x-form-drawer-link>
            </x-slot:actions>

            <x-slot:filter>
                <x-service-tag-filter
                    :all-tags="$allTags ?? []"
                    :selected-tags="$selectedTags ?? []"
                />
            </x-slot:filter>
        </x-resource-index-page>

        <x-horizon.delete-confirm-modal
            entity="Service"
            title="Delete service"
            resource-label="service"
        />
    </div>
@endsection
