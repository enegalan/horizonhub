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
            },
        }"
    >
        <x-resource-index-page
            eyebrow="Delivery channels"
            title="Notification providers"
            description="Connect Slack, Discord, or email destinations once, then attach them to alert rules when you need to notify a team."
            :filter-action="route('horizon.providers.index')"
            stats-target="horizon-provider-stats"
            :stats-columns="4"
            stats-view="horizon.providers.partials.index.stats"
            body-target="tbody-horizon-provider-list"
            body-view="horizon.providers.partials.index.tbody"
            :body-view-data="['providers' => $providers]"
            :defer="$defer ?? false"
        >
            <x-slot:actions>
                <x-form-drawer-link :href="route('horizon.providers.create')" class="h-9 shrink-0 text-sm">
                    New provider
                </x-form-drawer-link>
            </x-slot:actions>

            <x-slot:filter>
                <div class="min-w-0 flex-1 space-y-2">
                    <x-input-label for="providers-index-search">Search</x-input-label>
                    <x-text-input
                        id="providers-index-search"
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Provider name"
                        class="w-full min-w-0 sm:max-w-xs"
                    />
                </div>
            </x-slot:filter>
        </x-resource-index-page>

        <x-horizon.delete-confirm-modal
            entity="Provider"
            title="Delete provider"
            resource-label="provider"
        />
    </div>
@endsection
