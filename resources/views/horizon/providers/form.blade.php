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
                            $meta = $class::meta();
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
                            x-bind:placeholder="type === 'slack' ? 'e.g. Slack #ops' : (type === 'discord' ? 'e.g. Discord #alerts' : 'e.g. Alerts team')"
                            class="w-full"
                        />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>

                    <div
                        class="rounded-xl border px-4 py-4 transition-colors"
                        x-show="type === 'slack'"
                        x-cloak
                        :class="type === 'slack' ? 'border-violet-500/20 bg-violet-500/5' : 'border-border bg-muted/20'"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <x-icons.link class="size-4 text-violet-700 dark:text-violet-300" />
                            <p class="text-sm font-medium text-foreground">Slack webhook</p>
                        </div>
                        <div class="space-y-2">
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
                    </div>

                    <div
                        class="rounded-xl border px-4 py-4 transition-colors"
                        x-show="type === 'discord'"
                        x-cloak
                        :class="type === 'discord' ? 'border-indigo-500/20 bg-indigo-500/5' : 'border-border bg-muted/20'"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <x-icons.link class="size-4 text-indigo-700 dark:text-indigo-300" />
                            <p class="text-sm font-medium text-foreground">Discord webhook</p>
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="discord_webhook_url">Webhook URL</x-input-label>
                            <x-text-input
                                type="url"
                                id="discord_webhook_url"
                                name="webhook_url"
                                value="{{ old('webhook_url', $webhookUrl) }}"
                                placeholder="https://discord.com/api/webhooks/..."
                                class="w-full font-mono text-sm"
                            />
                            @error('webhook_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div
                        class="rounded-xl border px-4 py-4 transition-colors"
                        x-show="type === 'email'"
                        x-cloak
                        :class="type === 'email' ? 'border-sky-500/20 bg-sky-500/5' : 'border-border bg-muted/20'"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <x-icons.users class="size-4 text-sky-700 dark:text-sky-300" />
                            <p class="text-sm font-medium text-foreground">Email recipients</p>
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="email_to">Recipients (comma-separated)</x-input-label>
                            <x-text-input
                                type="text"
                                id="email_to"
                                name="email_to"
                                value="{{ old('email_to', $emailTo) }}"
                                placeholder="alerts@example.com, oncall@example.com"
                                class="w-full"
                            />
                            @error('email_to') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        </div>
                    </div>
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
