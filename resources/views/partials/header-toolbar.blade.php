<div class="flex shrink-0 items-center gap-1.5"
    x-data="{
        hotReloadEnabled: localStorage.getItem('horizonhub_hotreload') !== 'false',
        themePreference: window.horizonHubTheme.getStoredTheme(),
        toggleHotReload() {
            this.hotReloadEnabled = !this.hotReloadEnabled;
            localStorage.setItem('horizonhub_hotreload', this.hotReloadEnabled);
            window.dispatchEvent(new CustomEvent('horizonhub-hotreload-changed', { detail: { enabled: this.hotReloadEnabled } }));
        },
        cycleTheme() {
            this.themePreference = window.horizonHubTheme.cycleTheme();
        },
        syncThemePreference() {
            this.themePreference = window.horizonHubTheme.getStoredTheme();
        }
    }"
    @apply-theme.window="syncThemePreference()"
    >
    <x-button
        variant="ghost"
        type="button"
        @click="toggleHotReload()"
        class="h-9 w-9 shrink-0 border border-border border-1 p-0"
        x-bind:class="hotReloadEnabled ? 'text-primary bg-primary/10 hover:bg-primary/10' : 'text-muted-foreground hover:text-foreground'"
        x-bind:aria-pressed="hotReloadEnabled"
        aria-label="Toggle hot reload"
    >
        <x-heroicon-o-arrow-path class="size-4" />
    </x-button>
    <x-button
        variant="ghost"
        type="button"
        @click="cycleTheme()"
        class="h-9 w-9 border border-border border-1 shrink-0 p-0 text-muted-foreground hover:text-foreground"
        x-bind:aria-label="themePreference === 'light' ? 'Theme: light. Cycle theme' : themePreference === 'dark' ? 'Theme: dark. Cycle theme' : 'Theme: system. Cycle theme'"
    >
        <span class="relative inline-flex size-5 items-center justify-center">
            <x-heroicon-o-sun class="absolute size-5 transition-opacity" x-show="themePreference === 'light'" x-cloak />
            <x-heroicon-o-moon class="absolute size-5 transition-opacity" x-show="themePreference === 'dark'" x-cloak />
            <x-heroicon-o-computer-desktop class="absolute size-5 transition-opacity" x-show="themePreference === 'system'" x-cloak />
        </span>
    </x-button>
</div>
