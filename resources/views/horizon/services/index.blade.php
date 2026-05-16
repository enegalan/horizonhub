@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="window.horizonDeleteConfirm ? window.horizonDeleteConfirm('Service') : {}"
    >
        <div class="card overflow-hidden" x-data="window.horizonServicesList ? window.horizonServicesList() : {}" x-init="typeof init === 'function' && init()">
            <x-page-hero
                eyebrow="Connected Horizon instances"
                title="Services"
                description="Register each Horizon deployment, monitor its health, and open its dashboard when you need to inspect queues and workers."
            />

            <x-session-flash />

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
                    <div class="flex items-center">
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
