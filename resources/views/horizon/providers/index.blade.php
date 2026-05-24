@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.horizonDeleteConfirm ? window.horizonDeleteConfirm('Provider') : {}"
    >
        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Delivery channels"
                title="Notification providers"
                description="Connect Slack or email destinations once, then attach them to alert rules when you need to notify a team."
            >
                <x-slot:actions>
                    <x-form-drawer-link :href="route('horizon.providers.create')" class="h-9 shrink-0 text-sm">
                        New provider
                    </x-form-drawer-link>
                </x-slot:actions>
            </x-page-hero>

            <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-3 sm:px-6">
                <div
                    id="turbo-horizon-provider-stats"
                    class="contents"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.metric-columns />
                    @else
                        @include('horizon.providers.partials.index.stats')
                    @endif
                </div>
            </div>

            <div class="px-5 py-5 sm:px-6">
                <div
                    id="turbo-tbody-horizon-provider-list"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.card-grid />
                    @else
                        @include('horizon.providers.partials.index.tbody', ['providers' => $providers])
                    @endif
                </div>
            </div>
        </div>

        @include('horizon.providers.partials.index.delete-confirm-modal')
    </div>
@endsection
