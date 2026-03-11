<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        x-data="{
            theme: (window.__horizonhub_theme || 'light'),
            get dark() {
                if (this.theme === 'system') return window.matchMedia('(prefers-color-scheme: dark)').matches;
                return this.theme === 'dark';
            }
        }"
        x-init="document.documentElement.classList.toggle('dark', dark)"
        x-effect="document.documentElement.classList.toggle('dark', dark)"
        @theme-changed.window="theme = $event.detail; if (theme) { localStorage.setItem('horizonhub_theme', theme); window.__horizonhub_theme = theme }"
        @apply-theme.window="theme = (window.__horizonhub_theme || (function(){ var t = localStorage.getItem('horizonhub_theme'); if (t === 'light' || t === 'dark' || t === 'system') return t; })()); window.__horizonhub_theme = theme"
        @toggle-dark.window="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('horizonhub_theme', theme); window.__horizonhub_theme = theme">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
            (function () {
                var t = localStorage.getItem('horizonhub_theme');
                if (!t || (t !== 'light' && t !== 'dark' && t !== 'system')) t = 'light';
                window.__horizonhub_theme = t;
                var isDark = t === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : (t === 'dark');
                document.documentElement.classList.toggle('dark', isDark);
            })();
        </script>
        <title>{{ \config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="preload" href="{{ asset('logo.svg') }}" as="image">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            window.horizonHubHotReloadInterval = {{ (int) \config('horizonhub.hot_reload_interval') }};
            window.horizonHubHotReloadTimer = null;
            document.addEventListener('alpine:init', () => {
                Alpine.store('hotReload', {
                    enabled: localStorage.getItem('horizonhub_hotreload') !== 'false',
                    interval: Math.max(1, window.horizonHubHotReloadInterval || 5)
                });
                function tick() {
                    if (typeof Alpine !== 'undefined' && Alpine.store && Alpine.store('hotReload') && Alpine.store('hotReload').enabled) {
                        window.dispatchEvent(new CustomEvent('horizonhub-refresh'));
                    }
                }
                var sec = Math.max(1, (Alpine.store('hotReload').interval || window.horizonHubHotReloadInterval || 5));
                window.horizonHubHotReloadTimer = setInterval(tick, sec * 1000);
            });
        </script>
    </head>
    <body class="font-sans antialiased min-h-screen"
        x-data="{
            sidebarOpen: localStorage.getItem('horizon_sidebar_open') !== 'false'
        }"
        x-init="document.body.classList.toggle('sidebar-collapsed', !sidebarOpen)"
        x-effect="localStorage.setItem('horizon_sidebar_open', sidebarOpen); document.body.classList.toggle('sidebar-collapsed', !sidebarOpen)"
        @toggle-sidebar.window="sidebarOpen = !sidebarOpen; window.dispatchEvent(new CustomEvent('sidebar-open-changed', { detail: sidebarOpen }))">
        <script>
            (() => {
                var t = window.__horizonhub_theme || localStorage.getItem('horizonhub_theme');
                if (!t || (t !== 'light' && t !== 'dark' && t !== 'system')) t = 'light';
                window.__horizonhub_theme = t;
                var isDark = t === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : (t === 'dark');
                document.documentElement.classList.toggle('dark', isDark);
            })();
            document.body.addEventListener('click', e => {
                var btn = e.target.closest('[data-queue-action]');
                if (!btn || !btn.closest('[data-table-body="horizon-queue-list"]')) return;
                e.preventDefault();
                var action = btn.getAttribute('data-queue-action');
                var serviceId = btn.getAttribute('data-service-id');
                var queue = btn.getAttribute('data-queue');
                if (!action || !serviceId || !queue) return;
                var apiUrl = '/horizon/queues/' + encodeURIComponent(queue) + '/' + action;
                var token = document.querySelector('meta[name="csrf-token"]');
                btn.disabled = true;
                btn.setAttribute('data-loading', 'true');
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                    },
                    body: JSON.stringify({ service_id: serviceId })
                }).then(r => {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                    if (!r.ok) {
                        return r.json().then(body => {
                            if (window.toast) window.toast.error(body.message || 'Request failed');
                            else alert(body.message || 'Request failed');
                        }).catch(() => {
                            if (window.toast) window.toast.error('Request failed');
                            else alert('Request failed');
                        });
                    }
                    if (window.toast) window.toast.success(action === 'pause' ? 'Queue paused.' : 'Queue resumed.');
                    window.dispatchEvent(new CustomEvent('horizon-queue-action-done'));
                }).catch(() => {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                    if (window.toast) window.toast.error('Network error');
                    else alert('Network error');
                });
            });
        </script>
        <div class="app-layout flex min-h-screen flex-1 flex-row lg:flex-row">
            @include('partials.navigation')
            <div class="flex min-h-0 min-w-0 flex-1 flex-col pt-12 lg:pt-0">
            @if (isset($header))
                <header class="shrink-0 border-b border-border bg-card">
                    <div class="max-w-6xl mx-16 flex h-12 items-center">
                        <h1 class="text-page-title text-foreground">{{ $header }}</h1>
                    </div>
                </header>
            @endif
            <main class="flex-1 p-4 lg:p-6">
                @yield('content')
                @isset($slot)
                    {{ $slot }}
                @endisset
            </main>
            </div>
        </div>
        <div id="toaster" aria-live="polite"></div>
    </body>
</html>
