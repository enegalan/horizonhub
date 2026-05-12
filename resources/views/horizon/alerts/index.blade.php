@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            showDeleteAlertModal: false,
            deleteAlertName: '',
            deleteAlertAction: '',
            openDeleteAlertModal(name, action) {
                this.deleteAlertName = name;
                this.deleteAlertAction = action;
                this.showDeleteAlertModal = true;
            },
            closeDeleteAlertModal() {
                this.showDeleteAlertModal = false;
            },
            confirmDeleteAlert() {
                this.$refs.deleteAlertForm.requestSubmit();
                this.closeDeleteAlertModal();
            }
        }"
    >
        <div
            class="card overflow-hidden"
            x-data="window.horizonAlertsList ? window.horizonAlertsList() : {}"
            x-init="typeof init === 'function' && init()"
        >
            <div class="relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card px-5 py-5 sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Monitoring rules</p>
                        <h2 class="text-section-title text-foreground">Alert rules</h2>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Define when Horizon should notify your team about failures, blocked queues, slow jobs, or offline workers.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
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
                        <x-button
                            type="button"
                            class="h-9 shrink-0 text-sm"
                            onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
                        >
                            New alert
                        </x-button>
                    </div>
                </div>
            </div>

            @if(session('status'))
                <div class="border-b border-border px-5 py-3 text-sm text-muted-foreground sm:px-6">
                    {{ session('status') }}
                </div>
            @endif

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
