@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.horizonDeleteConfirm ? window.horizonDeleteConfirm('Service') : {}"
    >
        <div class="card overflow-hidden" x-data="window.horizonServicesList ? window.horizonServicesList() : {}" x-init="typeof init === 'function' && init()">
            <x-page-hero
                eyebrow="Connected Horizon instances"
                title="Services"
                description="Register each Horizon deployment, monitor its health, and open its dashboard when you need to inspect queues and workers."
            >
                <x-slot:actions>
                    <x-form-drawer-link :href="route('horizon.services.create')" class="h-9 shrink-0 text-sm">
                        Register service
                    </x-form-drawer-link>
                </x-slot:actions>
            </x-page-hero>

            <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
                <form method="GET" action="{{ route('horizon.services.index') }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="_top" data-service-tag-filter="1" data-service-tag-filter-manual="1">
                    <x-service-tag-filter
                        :all-tags="$allTags ?? []"
                        :selected-tags="$selectedTags ?? []"
                    />
                    <x-button type="submit" class="h-9 shrink-0 text-sm">
                        Search
                    </x-button>
                </form>
            </div>

            <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-3 sm:px-6">
                <div
                    id="turbo-horizon-service-stats"
                    class="contents"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.metric-columns />
                    @else
                        @include('horizon.services.partials.index.stats')
                    @endif
                </div>
            </div>

            <div class="px-5 py-5 sm:px-6">
                <div
                    id="turbo-tbody-horizon-service-list"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.card-grid />
                    @else
                        @include('horizon.services.partials.index.tbody', ['services' => $services])
                    @endif
                </div>
            </div>
        </div>

        @include('horizon.services.partials.index.delete-confirm-modal')
    </div>
@endsection
