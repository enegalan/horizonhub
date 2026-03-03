@php
    $sidebarStorageKey = 'horizon_sidebar_open';
@endphp
<div class="nav-sidebar-column shrink-0 lg:flex lg:min-h-screen lg:w-52 lg:flex-col"
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
         x-cloak></div>

    <div class="fixed top-0 left-0 right-0 z-30 flex h-12 items-center gap-2 border-b border-border bg-card/95 px-3 backdrop-blur-sm lg:hidden">
        <x-button variant="ghost" type="button" @click="drawerOpen = !drawerOpen" class="h-8 w-8 p-0" aria-label="Open menu">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </x-button>
        <img src="{{ asset('logo.svg') }}" alt="Horizon Hub" class="h-6 w-6 shrink-0 rounded-md object-contain">
        <span class="text-sm font-semibold text-foreground">Horizon Hub</span>
    </div>

    <aside class="aside-drawer fixed inset-y-0 left-0 z-50 flex w-52 flex-col border-r border-border bg-card transition-transform duration-300 ease-in-out lg:inset-auto lg:h-full lg:min-h-0 lg:translate-x-0 w-[inherit]"
        :class="(isLg ? sidebarOpen : drawerOpen) ? 'translate-x-0' : '-translate-x-full hidden'"
        style="will-change: transform;">
        <div class="flex h-12 min-h-12 shrink-0 items-center justify-between gap-2 border-b border-border px-3">
            <a href="{{ route('horizon.index') }}" wire:navigate class="flex min-w-0 items-center gap-2.5" @click="drawerOpen = false">
                <img src="{{ asset('logo.svg') }}" alt="Horizon Hub" class="h-6 w-6 shrink-0 rounded-md object-contain">
                <span class="truncate text-sm font-semibold text-foreground">Horizon Hub</span>
            </a>
            <x-button variant="ghost" type="button" @click="$dispatch('toggle-sidebar'); drawerOpen = false" class="hidden shrink-0 lg:inline-flex h-8 w-8 p-0" aria-label="Collapse sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </x-button>
        </div>
        <nav class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto overflow-x-hidden p-2">
            <a href="{{ route('horizon.index') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.index') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg>
                Jobs
            </a>
            <a href="{{ route('horizon.queues.index') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.queues.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h12M6 12h12M6 18h12"/></svg>
                Queues
            </a>
            <a href="{{ route('horizon.services.index') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.services.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z"/></svg>
                Services
            </a>
            <a href="{{ route('horizon.metrics') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.metrics') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                Metrics
            </a>
            <a href="{{ route('horizon.alerts.index') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.alerts.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
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
                 }
             }"
             x-cloak>
            <div class="flex items-center justify-between gap-2 px-2.5 py-1.5">
                <span class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Hot reload</span>
                <button type="button"
                        @click="toggle()"
                        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                        :class="enabled ? 'bg-primary' : 'bg-muted'"
                        :aria-pressed="enabled"
                        aria-label="Toggle hot reload">
                    <span class="inline-block h-4 w-4 transform rounded-full bg-background shadow transition-transform"
                          :class="enabled ? 'translate-x-4' : 'translate-x-0.5'"></span>
                </button>
            </div>
            <a href="{{ route('horizon.settings') }}" wire:navigate class="nav-side-link {{ request()->routeIs('horizon.settings') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </div>
    </aside>

    <div class="fixed left-4 top-4 z-40 hidden lg:block" x-show="isLg && !sidebarOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak>
        <x-button variant="secondary" type="button" @click="$dispatch('toggle-sidebar')" class="h-9 w-9 p-0" aria-label="Open sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </x-button>
    </div>

    <style>
    .aside-drawer { height: 100vh; height: 100dvh; min-height: 100vh; }
    @media (min-width: 1024px) { .aside-drawer { height: 100%; min-height: 0; } }
    [x-cloak] { display: none !important; }
    .nav-side-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.625rem;
        font-size: 0.8125rem;
        font-weight: 500;
        color: hsl(var(--muted-foreground));
        border-radius: var(--radius);
        transition: color 0.15s, background-color 0.15s;
    }
    .nav-side-link:hover {
        background-color: hsl(var(--accent));
        color: hsl(var(--accent-foreground));
    }
    .nav-side-link-active {
        background-color: hsl(var(--accent));
        color: hsl(var(--accent-foreground));
    }
    </style>
</div>
