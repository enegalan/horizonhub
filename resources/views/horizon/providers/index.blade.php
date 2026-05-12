@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            showDeleteProviderModal: false,
            deleteProviderName: '',
            deleteProviderAction: '',
            openDeleteProviderModal(name, action) {
                this.deleteProviderName = name;
                this.deleteProviderAction = action;
                this.showDeleteProviderModal = true;
            },
            closeDeleteProviderModal() {
                this.showDeleteProviderModal = false;
            },
            confirmDeleteProvider() {
                this.$refs.deleteProviderForm.requestSubmit();
                this.closeDeleteProviderModal();
            }
        }"
    >
        <div class="card overflow-hidden">
            <div class="relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card px-5 py-5 sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Delivery channels</p>
                        <h2 class="text-section-title text-foreground">Notification providers</h2>
                        <p class="max-w-2xl text-sm text-muted-foreground">
                            Connect Slack or email destinations once, then attach them to alert rules when you need to notify a team.
                        </p>
                    </div>
                    <x-button
                        type="button"
                        class="h-9 shrink-0 text-sm"
                        onclick="window.location.href='{{ route('horizon.providers.create') }}'"
                    >
                        New provider
                    </x-button>
                </div>
            </div>

            @if(session('status'))
                <div class="border-b border-border px-5 py-3 text-sm text-muted-foreground sm:px-6">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-3 sm:px-6">
                <div
                    id="turbo-horizon-provider-stats"
                    class="contents"
                    data-turbo-stream-patch-children="true"
                >
                    @if(!empty($defer))
                        <x-skeleton.metric-columns />
                    @else
                        @include('horizon.providers.partials.provider-stats')
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
                        @include('horizon.providers.partials.provider-tbody', ['providers' => $providers])
                    @endif
                </div>
            </div>
        </div>

        @include('horizon.providers.partials.delete-provider-confirm-modal')
    </div>
@endsection
