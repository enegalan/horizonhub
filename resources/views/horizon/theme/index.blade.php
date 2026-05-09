@extends('layouts.app')

@section('content')
    <div class="max-w-3xl">
        <div class="card">
            <div class="px-4 py-4">
                <h2 class="text-section-title text-foreground mb-3">Theme</h2>
                <p class="text-sm text-muted-foreground mb-4">Choose how Horizon Hub looks. You can pick a theme or use your system setting.</p>
                <div class="flex flex-wrap gap-2"
                    x-data="{ theme: window.horizonHubTheme.getStoredTheme() }"
                    @apply-theme.window="theme = window.horizonHubTheme.getStoredTheme()">
                    <button type="button"
                        @click="theme = window.horizonHubTheme.setTheme('light')"
                        :class="theme === 'light' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                        Light
                    </button>
                    <button type="button"
                        @click="theme = window.horizonHubTheme.setTheme('dark')"
                        :class="theme === 'dark' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                        Dark
                    </button>
                    <button type="button"
                        @click="theme = window.horizonHubTheme.setTheme('system')"
                        :class="theme === 'system' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                        class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors">
                        System
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
