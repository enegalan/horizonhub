@extends('layouts.app')

@section('content')
    <div class="max-w-md">
        <div class="card p-4">
            <h2 class="text-section-title text-foreground mb-3">Edit service</h2>
            <form method="POST" action="{{ route('horizon.services.update', $service) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="space-y-2">
                    <x-input-label>Name</x-input-label>
                    <x-text-input type="text" name="name" value="{{ old('name', $service->name) }}" class="w-full" />
                    @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-2">
                    <x-input-label>Base URL</x-input-label>
                    <x-text-input type="url" name="base_url" value="{{ old('base_url', $service->base_url) }}" class="w-full" />
                    @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        Internal URL used to obtain events from the service.
                    </p>
                </div>
                <div class="space-y-2">
                    <x-input-label>Public URL (optional)</x-input-label>
                    <x-text-input type="url" name="public_url" value="{{ old('public_url', $service->public_url) }}" class="w-full" />
                    @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        URL reachable from your browser.
                    </p>
                </div>
                <div class="flex gap-2 pt-1">
                    <x-button type="submit" class="h-9 text-sm relative inline-flex items-center justify-center">
                        Save
                    </x-button>
                    <x-button variant="ghost" type="button" class="h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                        Cancel
                    </x-button>
                </div>
            </form>
        </div>
    </div>
@endsection

