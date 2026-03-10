@extends('layouts.app')

@section('content')
    @php
        $isEdit = $provider->exists;
        $action = $isEdit ? route('horizon.providers.update', $provider) : route('horizon.providers.store');
        $currentType = old('type', $provider->type ?? \App\Models\NotificationProvider::TYPE_SLACK);
    @endphp

    <div class="max-w-2xl space-y-6" x-data="{ type: '{{ $currentType }}' }">
        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card">
                <div class="px-4 py-4 space-y-4">
                    <div class="space-y-2">
                        <x-input-label for="name">Name</x-input-label>
                        <x-text-input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $provider->name) }}"
                            placeholder="e.g. Slack #ops"
                            class="w-full"
                        />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label for="type">Type</x-input-label>
                        <x-select
                            id="type"
                            name="type"
                            class="w-full"
                            x-model="type"
                        >
                            <option value="slack" @selected($currentType === 'slack')>Slack</option>
                            <option value="email" @selected($currentType === 'email')>Email</option>
                        </x-select>
                        @error('type') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2" x-show="type === 'slack'">
                        <x-input-label for="webhook_url">Webhook URL</x-input-label>
                        <x-text-input
                            type="url"
                            id="webhook_url"
                            name="webhook_url"
                            value="{{ old('webhook_url', $webhookUrl) }}"
                            placeholder="https://hooks.slack.com/services/..."
                            class="w-full font-mono text-sm"
                        />
                        @error('webhook_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2" x-show="type === 'email'">
                        <x-input-label for="email_to">Recipients (comma-separated)</x-input-label>
                        <x-text-input
                            type="text"
                            id="email_to"
                            name="email_to"
                            value="{{ old('email_to', $emailTo) }}"
                            placeholder="alerts@example.com"
                            class="w-full"
                        />
                        @error('email_to') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <x-button
                    type="submit"
                    class="h-9 text-sm relative inline-flex items-center justify-center"
                >
                    Save
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.settings', ['tab' => 'providers']) }}'"
                >
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection

