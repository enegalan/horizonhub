@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.horizonDeleteConfirm ? window.horizonDeleteConfirm('Alert') : {}"
    >
        <div
            class="card overflow-hidden"
            x-data="window.horizonAlertsList ? window.horizonAlertsList() : {}"
            x-init="typeof init === 'function' && init()"
        >
            <x-page-hero
                eyebrow="Monitoring rules"
                title="Alert rules"
                description="Define when Horizon should notify your team about failures, blocked queues, slow jobs, or offline workers."
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
                                <x-heroicon-o-bell class="size-4 alert-evaluate-btn-icon" />
                                <x-heroicon-o-arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                                <span data-alert-evaluate-all-label>Evaluate all alerts</span>
                            </span>
                        </x-button>
                    @endif
                    <x-form-drawer-link :href="route('horizon.alerts.create')" class="h-9 shrink-0 text-sm">
                        New alert
                    </x-form-drawer-link>
                </x-slot:actions>
            </x-page-hero>

            <x-session-flash />

            <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-3 sm:px-6">
                <div
                    id="turbo-horizon-alert-stats"
                    class="contents"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.metric-columns />
                    @else
                        @include('horizon.alerts.partials.alert-stats')
                    @endif
                </div>
            </div>

            <div class="px-5 py-5 sm:px-6">
                <div
                    id="turbo-tbody-horizon-alerts-list"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.card-grid />
                    @else
                        @include('horizon.alerts.partials.alert-tbody', ['alerts' => $alerts])
                    @endif
                </div>
            </div>
        </div>

        @include('horizon.alerts.partials.delete-alert-confirm-modal')
    </div>
@endsection
