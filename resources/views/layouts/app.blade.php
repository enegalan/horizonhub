<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="sidebar-bootstrapping">
    <head>
        <!-- Meta -->
        <meta charset="utf-8">
        <meta name="description" content="{{ config('app.name') }} is a centralized dashboard for monitoring Laravel Horizon jobs across multiple services.">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="turbo-prefetch" content="false">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="view-transition" content="same-origin" />

        <script>
            (function () {
                try {
                    var theme = localStorage.getItem('horizonhub_theme');
                    var isDark = theme === 'dark'
                        || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                    document.documentElement.classList.toggle('dark', isDark);
                } catch (e) {}
            })();
        </script>

        <!-- Title -->
        <title>{{ config('app.name') }}</title>

        <!-- Links -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="preload" href="{{ asset('logo.svg') }}" as="image">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            window.horizonHubStreamsBaseUrl = {{ Js::from(url('/horizon/streams')) }};
        </script>
    </head>
    <body class="font-sans antialiased min-h-screen" data-app-path="{{ request()->path() }}"
        x-data="{
            sidebarOpen: localStorage.getItem('horizon_sidebar_open') !== 'false'
        }"
        x-init="document.body.classList.toggle('sidebar-collapsed', !sidebarOpen)"
        x-effect="localStorage.setItem('horizon_sidebar_open', sidebarOpen); document.body.classList.toggle('sidebar-collapsed', !sidebarOpen)"
        @toggle-sidebar.window="sidebarOpen = !sidebarOpen; window.dispatchEvent(new CustomEvent('sidebar-open-changed', { detail: sidebarOpen }))">
        <script>
            (function () {
                try {
                    var sidebarOpen = localStorage.getItem('horizon_sidebar_open') !== 'false';
                    var lg = window.matchMedia('(min-width: 1024px)').matches;
                    document.body.classList.toggle('sidebar-collapsed', !sidebarOpen);
                    if (!lg) {
                        document.documentElement.setAttribute('data-aside-prefers-hidden', '');
                    }
                } catch (e) {}
            })();
        </script>
        <div class="app-layout flex min-h-screen flex-1 flex-row lg:flex-row">
            @include('partials.navigation')
            <div class="flex min-h-0 min-w-0 flex-1 flex-col pt-12 lg:pt-0">
            @if (isset($header))
                <header class="shrink-0 border-b border-border bg-card">
                    <div class="mx-8 flex h-12 items-center justify-between gap-3">
                        <h1 class="min-w-0 truncate text-page-title text-foreground">{{ $header }}</h1>
                        @include('partials.header-toolbar')
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
    </body>
</html>
