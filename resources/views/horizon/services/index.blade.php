@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
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
        <div class="card overflow-hidden">
            <div class="relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card px-5 py-5 sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
                <div class="relative space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Connected Horizon instances</p>
                    <h2 class="text-section-title text-foreground">Services</h2>
                    <p class="max-w-2xl text-sm text-muted-foreground">
                        Register each Horizon deployment, monitor its health, and open its dashboard when you need to inspect queues and workers.
                    </p>
                </div>
            </div>

            @if(session('status'))
                <div class="border-b border-border px-5 py-3 text-sm text-muted-foreground sm:px-6">
                    {{ session('status') }}
                </div>
            @endif

            <div class="border-b border-border px-5 py-5 sm:px-6">
                <div class="mb-4 space-y-1">
                    <h3 class="text-sm font-semibold text-foreground">Register service</h3>
                    <p class="text-sm text-muted-foreground">Add the internal URL Horizon Hub should use to collect metrics and events.</p>
                </div>
                <form method="POST" action="{{ route('horizon.services.store') }}" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" name="name" value="{{ old('name') }}" placeholder="my-service" class="w-full" />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" name="base_url" value="{{ old('base_url') }}" placeholder="http://my-service" class="w-full font-mono text-sm" />
                        @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" name="public_url" value="{{ old('public_url') }}" placeholder="http://my-service:8080" class="w-full font-mono text-sm" />
                        @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            URL reachable from your browser.
                        </p>
                    </div>
                    <div class="flex items-end">
                        <x-button type="submit" class="h-9 w-full text-sm sm:w-auto">
                            Register
                        </x-button>
                    </div>
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
                        @include('horizon.services.partials.service-stats')
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
                        @include('horizon.services.partials.service-tbody', ['services' => $services])
                    @endif
                </div>
            </div>
        </div>

        @include('horizon.services.partials.delete-service-confirm-modal')
    </div>
@endsection
