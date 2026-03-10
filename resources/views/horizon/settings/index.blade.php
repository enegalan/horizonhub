@extends('layouts.app')

@section('content')
    <div class="max-w-3xl space-y-6"
        x-data="{
            tab: '{{ $tab }}',
            contentHeight: 280,
            measureMode: true,
            refName() { return this.tab + 'Panel'; },
            updateHeight() {
                const el = this.$refs[this.refName()];
                if (el) this.contentHeight = el.offsetHeight;
            }
        }"
        x-init="
            const url = new URL(window.location.href);
            $watch('tab', (value) => {
                url.searchParams.set('tab', value);
                window.history.replaceState({}, '', url.toString());
                measureMode = true;
                $nextTick(() => { updateHeight(); measureMode = false; });
            });
            $nextTick(() => updateHeight());
        "
    >
        <nav class="flex gap-1 border-b border-border">
            <button no-ring type="button"
                @click="tab = 'appearance'; $nextTick(() => window.dispatchEvent(new CustomEvent('apply-theme')))"
                :class="tab === 'appearance' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors">
                Appearance
            </button>
            <button no-ring type="button"
                @click="tab = 'providers'"
                :class="tab === 'providers' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors">
                Providers
            </button>
        </nav>

        <div class="relative overflow-hidden transition-[height] duration-200 ease-out"
            :style="measureMode ? 'min-height: ' + contentHeight + 'px' : 'height: ' + contentHeight + 'px'">
            <div x-show="tab === 'appearance'"
                x-ref="appearancePanel"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                :class="(measureMode && tab === 'appearance') ? 'relative' : 'absolute inset-x-0 top-0'"
                class="card">
                <div class="px-4 py-4">
                    <h2 class="text-section-title text-foreground mb-3">Theme</h2>
                    <p class="text-sm text-muted-foreground mb-4">Choose how Horizon Hub looks. You can pick a theme or use your system setting.</p>
                    <div class="flex flex-wrap gap-2"
                        x-data="{ theme: (window.__horizonhub_theme || 'light') }"
                        x-init="theme = (window.__horizonhub_theme || (function(){ var t = localStorage.getItem('horizonhub_theme'); return (t === 'light' || t === 'dark' || t === 'system') ? t : 'light'; })())"
                        @theme-changed.window="theme = $event.detail"
                        @apply-theme.window="theme = (window.__horizonhub_theme || (function(){ var t = localStorage.getItem('horizonhub_theme'); return (t === 'light' || t === 'dark' || t === 'system') ? t : 'light'; })())">
                        <button type="button"
                            @click="theme = 'light'; localStorage.setItem('horizonhub_theme', 'light'); window.dispatchEvent(new CustomEvent('theme-changed', { detail: 'light' })); window.dispatchEvent(new CustomEvent('apply-theme'));"
                            :class="theme === 'light' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                            Light
                        </button>
                        <button type="button"
                            @click="theme = 'dark'; localStorage.setItem('horizonhub_theme', 'dark'); window.dispatchEvent(new CustomEvent('theme-changed', { detail: 'dark' })); window.dispatchEvent(new CustomEvent('apply-theme'));"
                            :class="theme === 'dark' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                            Dark
                        </button>
                        <button type="button"
                            @click="theme = 'system'; localStorage.setItem('horizonhub_theme', 'system'); window.dispatchEvent(new CustomEvent('theme-changed', { detail: 'system' })); window.dispatchEvent(new CustomEvent('apply-theme'));"
                            :class="theme === 'system' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                            System
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="tab === 'providers'"
                x-ref="providersPanel"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                :class="(measureMode && tab === 'providers') ? 'relative' : 'absolute inset-x-0 top-0'"
                class="space-y-4">
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

                <div class="card">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-border bg-muted/50">
                                    <th class="table-header px-4 py-2.5">Name</th>
                                    <th class="table-header px-4 py-2.5">Type</th>
                                    <th class="table-header px-4 py-2.5">Config</th>
                                    <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse($providers as $provider)
                                    <tr class="transition-colors hover:bg-muted/30">
                                        <td class="px-4 py-2.5 text-sm font-medium">{{ $provider->name }}</td>
                                        <td class="px-4 py-2.5">
                                            @if($provider->type === 'slack')
                                                <span class="badge">Slack</span>
                                            @else
                                                <span class="badge">Email</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-sm text-muted-foreground font-mono max-w-xs truncate">
                                            @if($provider->type === 'slack')
                                                {{ $provider->getWebhookUrl() ?: '–' }}
                                            @else
                                                {{ implode(', ', $provider->getToEmails()) ?: '–' }}
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5" data-column-id="actions">
                                            <div class="flex items-center gap-2">
                                                <x-button
                                                    variant="ghost"
                                                    type="button"
                                                    class="h-8 min-h-8 p-2"
                                                    aria-label="Edit"
                                                    title="Edit"
                                                    onclick="window.location.href='{{ route('horizon.providers.edit', $provider) }}'"
                                                >
                                                    <x-heroicon-o-pencil-square class="size-4" />
                                                </x-button>
                                                <form method="POST" action="{{ route('horizon.providers.destroy', $provider) }}" onsubmit="return confirm('Delete provider {{ $provider->name }}?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button
                                                        variant="ghost"
                                                        type="submit"
                                                        class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                                                        aria-label="Delete"
                                                        title="Delete"
                                                    >
                                                        <x-heroicon-o-trash class="size-4" />
                                                    </x-button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state">
                                                <x-heroicon-o-bell class="empty-state-icon" />
                                                <p class="empty-state-title">No providers</p>
                                                <p class="empty-state-description">Create Slack or Email providers, then select them when creating alerts.</p>
                                                <x-button
                                                    type="button"
                                                    class="mt-3 h-9 text-sm"
                                                    onclick="window.location.href='{{ route('horizon.providers.create') }}'"
                                                >
                                                    New provider
                                                </x-button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
