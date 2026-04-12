@extends('layouts.app')

@section('content')
    <div
        class="card mb-4"
        x-data="window.horizonAlertsList ? window.horizonAlertsList() : {}"
        x-init="typeof init === 'function' && init()"
    >
        <div class="px-4 py-3 flex items-center justify-between">
            <h2 class="text-section-title text-foreground">Alert rules</h2>
            <div class="flex items-center gap-2">
                @if($alerts->where('enabled', true)->count() > 0)
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
                <x-button
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
                >
                    New alert
                </x-button>
            </div>
        </div>
        @if(session('status'))
            <div class="px-4 pb-3 text-xs text-muted-foreground">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="card">
        <x-table
            resizable-key="horizon-alerts-list"
            column-ids="name,service,rule_type,queue,job_type,enabled,last_triggered,actions"
            body-id="turbo-tbody-horizon-alerts-list"
        >
            <x-slot:head>
                <tr class="border-b border-border bg-muted/50">
                    <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                    <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                    <th class="table-header px-4 py-2.5" data-column-id="rule_type">Rule type</th>
                    <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                    <th class="table-header px-4 py-2.5" data-column-id="job_type">Job type</th>
                    <th class="table-header px-4 py-2.5" data-column-id="enabled">Enabled</th>
                    <th class="table-header px-4 py-2.5" data-column-id="last_triggered">Last triggered</th>
                    <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                </tr>
            </x-slot:head>
                    @include('horizon.alerts.partials.alert-tbody', ['alerts' => $alerts])
        </x-table>
    </div>
@endsection
