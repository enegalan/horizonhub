<turbo-frame id="form-drawer">
    <div class="form-drawer-body flex h-full min-h-0 flex-col font-sans antialiased">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
            <h2 class="min-w-0 truncate text-base font-semibold text-foreground">
                {{ $header }}
            </h2>
            <x-button
                type="button"
                variant="ghost"
                class="h-8 w-8 shrink-0 p-0"
                aria-label="Close"
                data-form-drawer-close="panel"
            >
                <x-icons.x-mark class="size-5" />
            </x-button>
        </div>
        <div class="form-drawer-scroll min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 py-4 sm:px-5 sm:py-5">
            @yield('content')
        </div>
    </div>
</turbo-frame>
