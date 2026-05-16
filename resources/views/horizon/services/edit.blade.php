@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="card overflow-hidden">
            <x-page-hero
                eyebrow="Update service"
                title="Edit service"
                description="Update the URLs Horizon Hub should use to reach this deployment and open its dashboard from your browser."
            />

        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-border px-5 py-4 sm:px-6">
                <h3 class="text-sm font-semibold text-foreground">Connection details</h3>
                <p class="mt-1 text-sm text-muted-foreground">Keep the internal base URL accurate so metrics and events continue to sync.</p>
            </div>
            <form method="POST" action="{{ route('horizon.services.update', $service) }}" class="space-y-5 px-5 py-5 sm:px-6">
                @csrf
                @method('PUT')
                <div class="space-y-2">
                    <x-input-label>Name</x-input-label>
                    <x-text-input type="text" name="name" value="{{ old('name', $service->name) }}" class="w-full" />
                    @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                    <x-input-label>Base URL</x-input-label>
                    <x-text-input type="url" name="base_url" value="{{ old('base_url', $service->getBaseUrl()) }}" class="w-full font-mono text-sm" />
                    @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        Internal URL used to obtain events from the service.
                    </p>
                </div>
                <div class="space-y-2 rounded-xl border border-border/70 bg-muted/20 px-4 py-4">
                    <x-input-label>Public URL (optional)</x-input-label>
                    <x-text-input type="url" name="public_url" value="{{ old('public_url', $service->getPublicUrl()) }}" class="w-full font-mono text-sm" />
                    @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        URL reachable from your browser.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" class="h-9 text-sm relative inline-flex items-center justify-center">
                        Save changes
                    </x-button>
                    <x-button variant="ghost" type="button" class="h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                        Cancel
                    </x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
