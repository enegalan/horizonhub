@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.deleteConfirm ? window.deleteConfirm('Alert') : {}"
    >
        <x-resource-index-page
            eyebrow="Monitoring rules"
            title="Alert rules"
            description="Define when Horizon should notify your team about failures, blocked queues, slow jobs, or offline workers."
            list-component="window.horizonAlertsList ? window.horizonAlertsList() : {}"
            :filter-action="route('horizon.alerts.index')"
            stats-target="horizon-alert-stats"
            :stats-columns="3"
            stats-view="horizon.alerts.partials.index.stats"
            body-target="tbody-horizon-alerts-list"
            body-view="horizon.alerts.partials.index.tbody"
            :body-view-data="['alerts' => $alerts]"
            :defer="$defer ?? false"
        >
            <x-slot:actions>
                @if($evaluateAllAlertsVisible ?? false)
                    <x-button
                        variant="secondary"
                        type="button"
                        class="h-9 text-sm alert-evaluate-btn"
                        data-alert-evaluate-all-button="1"
                        data-alert-evaluate-all-url="{{ route('horizon.alerts.evaluate-all') }}"
                        data-alert-evaluate-all-status-url="{{ route('horizon.alerts.evaluations.status', ['evaluationId' => '__EVALUATION_ID__']) }}"
                    >
                        <span class="inline-flex items-center gap-2">
                            <x-icons.bell class="size-4 alert-evaluate-btn-icon" />
                            <x-icons.arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                            <span data-alert-evaluate-all-label>Evaluate all alerts</span>
                        </span>
                    </x-button>
                @endif
                <x-form-drawer-link :href="route('horizon.alerts.create')" class="h-9 shrink-0 text-sm">
                    New alert
                </x-form-drawer-link>
            </x-slot:actions>

            <x-slot:filter>
                <div class="min-w-0 flex-1 space-y-2">
                    <x-input-label for="alerts-index-search">Search</x-input-label>
                    <x-text-input
                        id="alerts-index-search"
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Alert name"
                        class="w-full min-w-0 sm:max-w-xs"
                    />
                </div>
            </x-slot:filter>
        </x-resource-index-page>

        <x-horizon.delete-confirm-modal
            entity="Alert"
            title="Delete alert"
            resource-label="alert"
        />
    </div>
@endsection
