<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
        x-data="{
            theme: window.horizonHubTheme.getStoredTheme(),
        }"
        x-init="window.horizonHubTheme.applyFromPreference(theme)"
        x-effect="window.horizonHubTheme.applyFromPreference(theme)"
    >
    <head>
        <!-- Meta -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="turbo-prefetch" content="false">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="view-transition" content="same-origin" />

        <!-- Title -->
        <title>{{ \config('app.name') }}</title>

        <!-- Links -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="preload" href="{{ asset('logo.svg') }}" as="image">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
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
        <div class="app-layout flex min-h-screen flex-1 flex-row lg:flex-row">
            @include('partials.navigation')
            <div class="flex min-h-0 min-w-0 flex-1 flex-col pt-12 lg:pt-0">
            @if (isset($header))
                <header class="shrink-0 border-b border-border bg-card">
                    <div class="max-w-6xl mx-8 flex h-12 items-center">
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
    </body>
</html>
