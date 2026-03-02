<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        x-data="{
            theme: (function() {
                    const t = localStorage.getItem('horizon_hub_theme');
                    if (t) return t;
                    return localStorage.getItem('horizon_hub_dark') === 'true' ? 'dark' : 'light';
            })(),
            get dark() {
                    if (this.theme === 'system') return window.matchMedia('(prefers-color-scheme: dark)').matches;
                    return this.theme === 'dark';
            }
        }"
        x-init="document.documentElement.classList.toggle('dark', dark)"
        x-effect="document.documentElement.classList.toggle('dark', dark); $nextTick(() => { if (theme) localStorage.setItem('horizon_hub_theme', theme); })"
        @theme-changed.window="theme = $event.detail; if (theme) localStorage.setItem('horizon_hub_theme', theme)"
        @apply-theme.window="theme = localStorage.getItem('horizon_hub_theme') || (localStorage.getItem('horizon_hub_dark') === 'true' ? 'dark' : 'light')"
        @toggle-dark.window="theme = (theme === 'dark' ? 'light' : 'dark'); localStorage.setItem('horizon_hub_theme', theme); localStorage.setItem('horizon_hub_dark', theme === 'dark')">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
            (function () {
                var t = localStorage.getItem('horizon_hub_theme');
                if (!t) t = localStorage.getItem('horizon_hub_dark') === 'true' ? 'dark' : 'light';
                var isDark = t === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : (t === 'dark');
                document.documentElement.classList.toggle('dark', isDark);
            })();
        </script>
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            window.horizonHubHotReloadInterval = {{ (int) config('horizon_hub.hot_reload_interval', 5) }};
            window.horizonHubHotReloadTimer = null;
            document.addEventListener('alpine:init', function () {
                Alpine.store('hotReload', {
                    enabled: localStorage.getItem('horizon_hub_hotreload') !== 'false',
                    interval: Math.max(1, window.horizonHubHotReloadInterval || 5)
                });
                function tick() {
                    if (typeof Alpine !== 'undefined' && Alpine.store && Alpine.store('hotReload') && Alpine.store('hotReload').enabled) {
                        window.dispatchEvent(new CustomEvent('horizon-hub-refresh'));
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
            (function () {
                var t = localStorage.getItem('horizon_hub_theme');
                if (!t) t = localStorage.getItem('horizon_hub_dark') === 'true' ? 'dark' : 'light';
                var isDark = t === 'system' ? window.matchMedia('(prefers-color-scheme: dark)').matches : (t === 'dark');
                document.documentElement.classList.toggle('dark', isDark);
            })();
            window.horizonQueuesBodyUrl = '{{ route("horizon.queues.body") }}';
            window.addEventListener('horizon-hub-refresh', function () {
                var main = document.querySelector('main');
                if (!main) return;
                if (main.querySelector('table[data-resizable-table]') || window.horizonTableInteracting) return;
                if (!window.Livewire) return;
                var el = main.querySelector('[wire\\:id]');
                if (!el) return;
                var id = el.getAttribute('wire:id');
                if (id) {
                    try {
                        var component = window.Livewire.find(id);
                        if (component && component.$wire && typeof component.$wire.$refresh === 'function') {
                            component.$wire.$refresh();
                        }
                    } catch (e) {}
                }
            });
            document.body.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-queue-action]');
                if (!btn || !btn.closest('[data-table-body="horizon-queue-list"]')) return;
                e.preventDefault();
                var action = btn.getAttribute('data-queue-action');
                var serviceId = btn.getAttribute('data-service-id');
                var queue = btn.getAttribute('data-queue');
                if (!action || !serviceId || !queue) return;
                var apiUrl = '/api/v1/queues/' + encodeURIComponent(queue) + '/' + action;
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
                }).then(function (r) {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                    if (!r.ok) {
                        return r.json().then(function (body) {
                            if (window.toast) window.toast.error(body.message || 'Request failed');
                            else alert(body.message || 'Request failed');
                        }).catch(function () {
                            if (window.toast) window.toast.error('Request failed');
                            else alert('Request failed');
                        });
                    }
                    if (window.toast) window.toast.success(action === 'pause' ? 'Queue paused.' : 'Queue resumed.');
                    window.dispatchEvent(new CustomEvent('horizon-queue-action-done'));
                }).catch(function () {
                    btn.disabled = false;
                    btn.removeAttribute('data-loading');
                    if (window.toast) window.toast.error('Network error');
                    else alert('Network error');
                });
            });
        </script>
        <div class="app-layout flex min-h-screen flex-1 flex-row lg:flex-row">
            <livewire:layout.navigation />
            <div class="main-content flex min-h-0 min-w-0 flex-1 flex-col pt-12 lg:pt-0">
            @if (isset($header))
                <header class="shrink-0 border-b border-border bg-card/95 backdrop-blur-sm">
                    <div class="max-w-6xl mx-4 flex h-12 items-center">
                        <h1 class="text-page-title text-foreground">{{ $header }}</h1>
                    </div>
                </header>
            @endif
            <main class="flex-1 p-4 lg:p-6">
                {{ $slot }}
            </main>
            </div>
        </div>
        <div id="toaster" aria-live="polite"></div>
    </body>
</html>
