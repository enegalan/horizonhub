@extends('layouts.app')

@section('content')
    <div class="space-y-4"
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
        <div class="card">
            <div class="px-4 py-3 flex items-center justify-between">
                <h2 class="text-section-title text-foreground">Notification providers</h2>
                <x-button
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.providers.create') }}'"
                >
                    New provider
                </x-button>
            </div>
            @if(session('status'))
                <div class="px-4 pb-3 text-xs text-muted-foreground">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <div @class(['card'])>
            <x-table
                resizable-key="horizon-settings-providers"
                column-ids="name,type,config,actions"
                body-id="turbo-tbody-horizon-provider-list"
                stream-patch-children
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                        <th class="table-header px-4 py-2.5" data-column-id="type">Type</th>
                        <th class="table-header px-4 py-2.5" data-column-id="config">Config</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions" data-sortable="false">Actions</th>
                    </tr>
                </x-slot:head>
                @if(!empty($defer))
                    <x-skeleton.table-rows rows="6" columns="4" />
                @else
                    @include('horizon.providers.partials.provider-tbody', ['providers' => $providers])
                @endif
            </x-table>
        </div>

        @include('horizon.providers.partials.delete-provider-confirm-modal')
    </div>
@endsection
