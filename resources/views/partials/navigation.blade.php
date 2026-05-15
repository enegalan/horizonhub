@php
    $sidebarStorageKey = 'horizon_sidebar_open';
@endphp
<div class="nav-sidebar-column shrink-0 lg:flex lg:min-h-screen lg:flex-col"
    x-data="{
        drawerOpen: false,
        sidebarOpen: localStorage.getItem('{{ $sidebarStorageKey }}') !== 'false',
        isLg: typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches
    }"
    x-init="
        document.documentElement.removeAttribute('data-aside-prefers-hidden');
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('sidebar-bootstrapping');
            });
        });
        window.addEventListener('resize', () => { isLg = window.matchMedia('(min-width: 1024px)').matches });
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
        <a href="{{ route('horizon.index') }}" class="flex min-w-0 items-center gap-2.5" @click="drawerOpen = false">
            <img src="{{ asset('logo.svg') }}" alt="{{ config('app.name') }}" class="h-6 w-6 shrink-0 rounded-md object-contain">
            <span class="truncate text-sm font-semibold text-foreground">{{ config('app.name') }}</span>
        </a>
    </div>

    <aside class="aside-drawer w-[inherit] fixed inset-y-0 left-0 z-50 flex flex-col border-r border-border bg-card transition-[transform,width] duration-[380ms] ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none lg:inset-auto lg:h-full lg:min-h-0"
        :class="!isLg && !drawerOpen ? '-translate-x-full pointer-events-none' : 'translate-x-0'"
        style="will-change: transform;">
        <div class="aside-drawer-header flex h-12 min-h-12 shrink-0 items-center gap-8 border-b border-border justify-between px-5">
            <a href="{{ route('horizon.index') }}" class="aside-drawer-header-brand-link flex min-w-0 items-center gap-2.5" @click="drawerOpen = false">
                <img src="{{ asset('logo.svg') }}" alt="{{ config('app.name') }}" class="h-6 w-6 shrink-0 rounded-md object-contain">
                <span class="aside-drawer-header-brand-text truncate text-sm font-semibold text-foreground">{{ config('app.name') }}</span>
            </a>
            <button type="button" class="aside-drawer-header-expand-btn group relative h-9 w-9 shrink-0 items-center justify-center rounded-md p-0 outline-none ring-offset-background transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" @click="$dispatch('toggle-sidebar')" aria-label="Expand sidebar">
                <img src="{{ asset('logo.svg') }}" alt="" class="h-6 w-6 rounded-md object-contain transition-opacity duration-150 group-hover:opacity-0" width="24" height="24">
                <span class="pointer-events-none absolute inset-0 flex items-center justify-center opacity-0 transition-opacity duration-150 group-hover:opacity-100 text-muted-foreground" aria-hidden="true">
                    <x-icons.sidebar-panel class="h-5 w-5" width="100%" height="100%" />
                </span>
            </button>
            <button type="button" class="aside-drawer-header-collapse-btn h-9 w-9 shrink-0 cursor-w-resize items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" @click="$dispatch('toggle-sidebar')" aria-label="Collapse sidebar">
                <x-icons.sidebar-panel class="h-5 w-5" width="100%" height="100%" />
            </button>
        </div>
        <nav
            class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto overflow-x-hidden py-2 px-4 "
            :class="isLg && !sidebarOpen ? 'items-center' : ''"
        >
            <a href="{{ route('horizon.index') }}" class="nav-side-link {{ request()->routeIs('horizon.index') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-squares-2x2 class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Dashboard</span>
            </a>
            <a href="{{ route('horizon.jobs.index') }}" class="nav-side-link {{ request()->routeIs('horizon.jobs.index') || request()->routeIs('horizon.jobs.show') || request()->routeIs('horizon.jobs.failed') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-clipboard-document-list class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Jobs</span>
            </a>
            <a href="{{ route('horizon.queues.index') }}" class="nav-side-link {{ request()->routeIs('horizon.queues.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-queue-list class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Queues</span>
            </a>
            <a href="{{ route('horizon.services.index') }}" class="nav-side-link {{ request()->routeIs('horizon.services.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-server-stack class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Services</span>
            </a>
            <a href="{{ route('horizon.metrics') }}" class="nav-side-link {{ request()->routeIs('horizon.metrics') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-chart-bar class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Metrics</span>
            </a>
            <a href="{{ route('horizon.alerts.index') }}" class="nav-side-link {{ request()->routeIs('horizon.alerts.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-bell class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Alerts</span>
            </a>
            <a href="{{ route('horizon.providers.index') }}" class="nav-side-link {{ request()->routeIs('horizon.providers.*') ? 'nav-side-link-active' : '' }}" @click="drawerOpen = false">
                <x-heroicon-o-megaphone class="h-4 w-4 shrink-0" />
                <span class="nav-side-link-label truncate">Providers</span>
            </a>
        </nav>
    </aside>

</div>
