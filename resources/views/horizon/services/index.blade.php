@extends('layouts.app')

@section('content')
    <div
        x-data="{
            showDeleteServiceModal: false,
            deleteServiceName: '',
            deleteServiceAction: '',
            openDeleteServiceModal(name, action) {
                this.deleteServiceName = name;
                this.deleteServiceAction = action;
                this.showDeleteServiceModal = true;
            },
            closeDeleteServiceModal() {
                this.showDeleteServiceModal = false;
            },
            confirmDeleteService() {
                this.$refs.deleteServiceForm.requestSubmit();
                this.closeDeleteServiceModal();
            }
        }"
    >
        <div class="card mb-4">
            <div class="px-4 py-3">
                <h2 class="text-section-title text-foreground mb-3">Register service</h2>
                @if(session('status'))
                    <p class="mb-2 text-xs text-muted-foreground">{{ session('status') }}</p>
                @endif
                <form method="POST" action="{{ route('horizon.services.store') }}" class="space-y-3 max-w-sm">
                    @csrf
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" name="name" value="{{ old('name') }}" placeholder="my-service" class="w-full" />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" name="base_url" value="{{ old('base_url') }}" placeholder="http://my-service" class="w-full" />
                        @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" name="public_url" value="{{ old('public_url') }}" placeholder="http://my-service:8080" class="w-full" />
                        @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            URL reachable from your browser.
                        </p>
                    </div>
                    <x-button type="submit" class="h-9 text-sm relative inline-flex items-center justify-center">
                        Register
                    </x-button>
                </form>
            </div>
        </div>

        <div class="card">
            <x-table
                resizable-key="horizon-service-list"
                column-ids="name,base_url,status,horizon_status,jobs,failed,last_seen,actions"
                body-id="turbo-tbody-horizon-service-list"
                stream-patch-children
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                        <th class="table-header px-4 py-2.5" data-column-id="base_url">Base URL</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="horizon_status">Horizon Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                        <th class="table-header px-4 py-2.5" data-column-id="failed">Failed</th>
                        <th class="table-header px-4 py-2.5" data-column-id="last_seen">Last seen</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                    </tr>
                </x-slot:head>
                        @include('horizon.services.partials.service-tbody', ['services' => $services])
            </x-table>
        </div>

        @include('horizon.services.partials.delete-service-confirm-modal')
    </div>
@endsection
