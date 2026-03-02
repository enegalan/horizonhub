<div class="max-w-3xl space-y-6" x-data="{ tab: @entangle('tab') }">
    <nav class="flex gap-1 border-b border-border">
        <button type="button"
                @click="tab = 'appearance'"
                :class="tab === 'appearance' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors">
            Appearance
        </button>
        <button type="button"
                @click="tab = 'alerts'"
                :class="tab === 'alerts' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors">
            Alerts
        </button>
        <button type="button"
                @click="tab = 'providers'"
                :class="tab === 'providers' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                class="border-b-2 px-3 py-2 text-sm font-medium transition-colors">
            Providers
        </button>
    </nav>

    <div x-show="tab === 'appearance'" class="card" x-cloak x-transition>
        <div class="px-4 py-4">
            <h2 class="text-section-title text-foreground mb-3">Theme</h2>
            <p class="text-sm text-muted-foreground mb-4">Choose how Horizon Hub looks. You can pick a theme or use your system setting.</p>
            <div class="flex flex-wrap gap-2"
                x-data="{
                    theme: (function() {
                        const t = localStorage.getItem('horizon_hub_theme');
                        if (t) return t;
                        return localStorage.getItem('horizon_hub_dark') === 'true' ? 'dark' : 'light';
                    })()
                }"
                @theme-changed.window="theme = $event.detail">
                <button type="button"
                        @click="theme = 'light'; localStorage.setItem('horizon_hub_theme', 'light'); $dispatch('theme-changed', 'light'); $dispatch('apply-theme')"
                        :class="theme === 'light' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                    Light
                </button>
                <button type="button"
                        @click="theme = 'dark'; localStorage.setItem('horizon_hub_theme', 'dark'); $dispatch('theme-changed', 'dark'); $dispatch('apply-theme')"
                        :class="theme === 'dark' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                    Dark
                </button>
                <button type="button"
                        @click="theme = 'system'; localStorage.setItem('horizon_hub_theme', 'system'); $dispatch('theme-changed', 'system'); $dispatch('apply-theme')"
                        :class="theme === 'system' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                    System
                </button>
            </div>
        </div>
    </div>

    <div x-show="tab === 'alerts'" class="card" x-cloak x-transition>
        <div class="px-4 py-4">
            <h2 class="text-section-title text-foreground mb-3">Alert email throttle</h2>
            <p class="text-sm text-muted-foreground mb-4">Minimum minutes between alert emails. Multiple failed jobs in that window are combined into one email. Use 0 to send on every trigger.</p>
            <form wire:submit="saveAlerts" class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1.5">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground" for="alert_email_interval_minutes">Minutes between emails</x-input-label>
                    <x-text-input type="number"
                                  id="alert_email_interval_minutes"
                                  wire:model="alert_email_interval_minutes"
                                  min="0"
                                  max="1440"
                                  class="w-24" />
                    @error('alert_email_interval_minutes')
                        <p class="text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="submit" class="h-9 text-sm">Save</x-ui.button>
            </form>
            @if($alert_email_interval_minutes === '0')
                <p class="text-xs text-muted-foreground mt-2">With 0, one email is sent per trigger (no batching).</p>
            @endif
        </div>
    </div>

    <div x-show="tab === 'providers'" class="space-y-4" x-cloak x-transition>
        <div class="card">
            <div class="px-4 py-3 flex items-center justify-between">
                <h2 class="text-section-title text-foreground">Notification providers</h2>
                <a href="{{ route('horizon.providers.create') }}" wire:navigate>
                    <x-ui.button type="button" class="h-9 text-sm">New provider</x-ui.button>
                </a>
            </div>
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
                                <td class="px-4 py-2.5 text-sm text-muted-foreground font-mono text-xs max-w-xs truncate">
                                    @if($provider->type === 'slack')
                                        {{ $provider->getWebhookUrl() ?: '–' }}
                                    @else
                                        {{ implode(', ', $provider->getToEmails()) ?: '–' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2.5" data-column-id="actions">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('horizon.providers.edit', $provider) }}" wire:navigate>
                                            <x-ui.button variant="ghost" type="button" class="h-8 min-h-8 p-2" aria-label="Edit" title="Edit">
                                                <x-heroicon-o-pencil-square class="size-4" />
                                            </x-ui.button>
                                        </a>
                                        <x-ui.button variant="ghost" type="button" wire:click="confirmDeleteProvider({{ $provider->id }})" class="h-8 min-h-8 p-2 text-destructive hover:text-destructive" aria-label="Delete" title="Delete">
                                            <x-heroicon-o-trash class="size-4" />
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                        <p class="empty-state-title">No providers</p>
                                        <p class="empty-state-description">Create Slack or Email providers, then select them when creating alerts.</p>
                                        <a href="{{ route('horizon.providers.create') }}" wire:navigate>
                                            <x-ui.button type="button" class="mt-3 h-9 text-sm">New provider</x-ui.button>
                                        </a>
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

@if($confirmingProviderId)
    <x-ui.confirm-modal
        title="Delete provider"
        :message="'Are you sure you want to delete provider &quot;' . $confirmingProviderName . '&quot;? Alerts using it will stop notifying through it.'"
        variant="danger"
        size="sm"
        confirmText="Delete"
        cancelText="Cancel"
        confirmAction="performDeleteProvider"
        cancelAction="cancelDeleteProvider"
        backdropAction="cancelDeleteProvider"
    />
@endif

@script
<script>
    window.addEventListener('horizon-hub-refresh', function () {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
