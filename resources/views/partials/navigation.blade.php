@php
    $sidebarStorageKey = 'horizon_sidebar_open';
@endphp
<div class="nav-sidebar-column shrink-0 lg:flex lg:min-h-screen lg:w-[248px] lg:flex-col"
    x-data="{
        drawerOpen: false,
        sidebarOpen: localStorage.getItem('{{ $sidebarStorageKey }}') !== 'false',
        isLg: false
    }"
    x-init="
        isLg = window.innerWidth >= 1024;
        window.addEventListener('resize', () => { isLg = window.innerWidth >= 1024 });
        window.addEventListener('sidebar-open-changed', e => { sidebarOpen = e.detail });
    "
    @keydown.escape.window="drawerOpen = false">
    <div x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="drawerOpen = false"
        ></div>

    <div class="fixed top-0 left-0 right-0 z-30 flex h-12 items-center gap-2 border-b border-border bg-card px-3 lg:hidden">
        <x-button variant="ghost" type="button" @click="drawerOpen = !drawerOpen" class="h-8 w-8 p-0" aria-label="Open menu">
            <x-heroicon-o-bars-3 class="size-6" />
        </x-button>
        <img src="{{ asset('logo.svg') }}" alt="Horizon Hub" class="h-6 w-6 shrink-0 rounded-md object-contain">
        <span class="text-sm font-semibold text-foreground">Horizon Hub</span>
    </div>

    <aside class="aside-drawer fixed inset-y-0 left-0 z-50 flex flex-col border-r border-border bg-card transition-transform duration-300 ease-in-out lg:inset-auto lg:h-full lg:min-h-0 lg:translate-x-0 w-[inherit]"
        :class="(isLg ? sidebarOpen : drawerOpen) ? 'translate-x-0' : '-translate-x-full hidden'"
        style="will-change: transform;">
        <div class="flex h-12 min-h-12 shrink-0 items-center justify-between gap-2 border-b border-border px-3">
            <a href="{{ route('horizon.index') }}" class="flex min-w-0 items-center gap-2.5" @click="drawerOpen = false">
                <img src="{{ asset('logo.svg') }}" alt="Horizon Hub" class="h-6 w-6 shrink-0 rounded-md object-contain">
                <span class="truncate text-sm font-semibold text-foreground">Horizon Hub</span>
            </a>
        </div>
        <nav class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto overflow-x-hidden p-2">
            <a href="{{ route('horizon.index') }}" class="nav-side-link {{ request()->routeIs('horizon.index') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-clipboard-document-list class="h-4 w-4 shrink-0" />
                Jobs
            </a>
            <a href="{{ route('horizon.queues.index') }}" class="nav-side-link {{ request()->routeIs('horizon.queues.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-queue-list class="h-4 w-4 shrink-0" />
                Queues
            </a>
            <a href="{{ route('horizon.services.index') }}" class="nav-side-link {{ request()->routeIs('horizon.services.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-server-stack class="h-4 w-4 shrink-0" />
                Services
            </a>
            <a href="{{ route('horizon.metrics') }}" class="nav-side-link {{ request()->routeIs('horizon.metrics') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-chart-bar class="h-4 w-4 shrink-0" />
                Metrics
            </a>
            <a href="{{ route('horizon.alerts.index') }}" class="nav-side-link {{ request()->routeIs('horizon.alerts.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-bell class="h-4 w-4 shrink-0" />
                Alerts
            </a>
        </nav>
        <div class="shrink-0 border-t border-border p-2 space-y-1"
            x-data="{
                enabled: localStorage.getItem('horizonhub_hotreload') !== 'false',
                toggle() {
                    this.enabled = !this.enabled;
                    localStorage.setItem('horizonhub_hotreload', this.enabled);
                    if (typeof $store !== 'undefined' && $store.hotReload) {
                        $store.hotReload.enabled = this.enabled;
                    }
                    if (typeof window !== 'undefined') {
                        window.dispatchEvent(new CustomEvent('horizonhub-hotreload-changed', { detail: { enabled: this.enabled } }));
                    }
                }
            }"
            >
            <div class="flex items-center justify-between gap-2 px-2.5 py-1.5">
                <span class="label-muted">Hot reload</span>
                <x-button
                    variant="none"
                    type="button"
                    @click="toggle()"
                    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors"
                    x-bind:class="enabled ? 'bg-primary' : 'bg-muted'"
                    x-bind:aria-pressed="enabled"
                    aria-label="Toggle hot reload"
                >
                    <span class="inline-block h-4 w-4 transform rounded-full bg-background shadow transition-transform"
                        :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"></span>
                </x-button>
            </div>
            <a href="{{ route('horizon.settings') }}" class="nav-side-link {{ request()->routeIs('horizon.settings') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-cog-6-tooth class="h-4 w-4 shrink-0" />
                Settings
            </a>
        </div>
    </aside>

    <div class="fixed left-4 top-4 z-40 hidden lg:block" x-show="isLg && !sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <x-button variant="secondary" type="button" @click="$dispatch('toggle-sidebar')" class="h-9 w-9 p-0" aria-label="Open sidebar">
            <x-heroicon-o-bars-3 class="size-6" />
        </x-button>
    </div>

</div>
