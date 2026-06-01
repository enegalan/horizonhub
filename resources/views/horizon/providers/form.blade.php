@extends('layouts.form-drawer')

@section('content')
    @php
        $isEdit = $provider->exists;
        $action = $isEdit ? route('horizon.providers.update', $provider) : route('horizon.providers.store');
        $currentType = $provider->type ?? \array_key_first(\App\Models\NotificationProvider::getProviders());
    @endphp

    <div class="space-y-6" x-data="{ type: '{{ $currentType }}' }">
        <form method="POST" action="{{ $action }}" class="space-y-6" data-turbo-frame="form-drawer">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Channel</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Choose where alert notifications should be delivered.</p>
                </div>
                <div class="grid gap-3 px-5 py-5 sm:grid-cols-3 sm:px-2">
                    @foreach(\App\Models\NotificationProvider::getProviders() as $type => $class)
                        @php
                            $meta = (new \App\Models\NotificationProvider(['type' => $type]))->meta();
                        @endphp
                        <label
                            class="relative cursor-pointer rounded-xl border p-2 transition-colors"
                            :class="type === '{{ $type }}'
                                ? 'border-{{ $meta['color'] }}-500/50 bg-{{ $meta['color'] }}-500/5 ring-1 ring-{{ $meta['color'] }}-500/20'
                                : 'border-border bg-card hover:border-{{ $meta['color'] }}-500/30 hover:bg-{{ $meta['color'] }}-500/5'"
                        >
                            <input
                                type="radio"
                                name="type"
                                value="{{ $type }}"
                                class="sr-only"
                                x-model="type"
                                @checked($currentType === $type)
                            >
                            <div class="flex items-center gap-2">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-{{ $meta['color'] }}-500/20 bg-{{ $meta['color'] }}-500/10 text-{{ $meta['color'] }}-700 dark:text-{{ $meta['color'] }}-300">
                                    <x-dynamic-component :component="'icons.' . $meta['icon']" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground">{{ $meta['label'] }}</p>
                                </div>
                                <x-info-tooltip text="{{ $meta['description'] }}" />
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="px-5 pb-5 text-xs text-destructive sm:px-6">{{ $message }}</p> @enderror
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h3 class="text-sm font-semibold text-foreground">Details</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Use a name your team will recognize when attaching this provider to alerts.</p>
                </div>
                <div class="space-y-5 px-5 py-5 sm:px-6">
                    <div class="space-y-2">
                        <x-input-label for="name">Name</x-input-label>
                        <x-text-input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $provider->name) }}"
                            placeholder="e.g. Ops alerts"
                            class="w-full"
                        />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    @foreach(\App\Models\NotificationProvider::getProviders() as $type => $class)
                        @php
                            $typeProvider = new \App\Models\NotificationProvider(['type' => $type]);
                            $meta = $typeProvider->meta();
                            $color = $meta['color'];
                        @endphp

                        @if($typeProvider->usesWebhook())
                            <div
                                class="rounded-xl border px-4 py-4 transition-colors"
                                x-show="type === '{{ $type }}'"
                                x-cloak
                                :class="type === '{{ $type }}' ? 'border-{{ $color }}-500/20 bg-{{ $color }}-500/5' : 'border-border bg-muted/20'"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <x-icons.link class="size-4 text-{{ $color }}-700 dark:text-{{ $color }}-300" />
                                    <p class="text-sm font-medium text-foreground">{{ $meta['label'] }} webhook</p>
                                </div>
                                <div class="space-y-2">
                                    <x-input-label for="webhook_url_{{ $type }}">Webhook URL</x-input-label>
                                    <x-text-input
                                        type="url"
                                        id="webhook_url_{{ $type }}"
                                        name="webhook_url"
                                        value="{{ old('webhook_url', ($provider->type ?? '') === $type ? $provider->getWebhookUrl() : '') }}"
                                        placeholder="https://..."
                                        class="w-full font-mono text-sm"
                                        x-bind:disabled="type !== '{{ $type }}'"
                                    />
                                    @error('webhook_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @elseif($typeProvider->usesMailing())
                            <div
                                class="rounded-xl border px-4 py-4 transition-colors"
                                x-show="type === '{{ $type }}'"
                                x-cloak
                                :class="type === '{{ $type }}' ? 'border-{{ $color }}-500/20 bg-{{ $color }}-500/5' : 'border-border bg-muted/20'"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <x-icons.users class="size-4 text-{{ $color }}-700 dark:text-{{ $color }}-300" />
                                    <p class="text-sm font-medium text-foreground">Email recipients</p>
                                </div>
                                <div class="space-y-2">
                                    <x-input-label for="email_to_{{ $type }}">Recipients (comma-separated)</x-input-label>
                                    <x-text-input
                                        type="text"
                                        id="email_to_{{ $type }}"
                                        name="email_to"
                                        value="{{ old('email_to', ($provider->type ?? '') === $type ? implode(', ', $provider->getToEmails()) : '') }}"
                                        placeholder="alerts@example.com, oncall@example.com"
                                        class="w-full"
                                        x-bind:disabled="type !== '{{ $type }}'"
                                    />
                                    @error('email_to') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button
                    type="submit"
                    class="h-9 text-sm relative inline-flex items-center justify-center"
                >
                    {{ $isEdit ? 'Save changes' : 'Create provider' }}
                </x-button>
                <x-button variant="ghost" type="button" class="h-9 text-sm" data-form-drawer-close>
                    Cancel
                </x-button>
            </div>
        </form>
    </div>
@endsection
