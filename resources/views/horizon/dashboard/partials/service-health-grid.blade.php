@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Service>|\App\Models\Service[] $services */
    $services ??= collect();
@endphp
@forelse($services as $service)
    @php
        $svcSt = \strtolower((string) ($service->status ?? ''));
        if ($svcSt === 'online') {
            $svcDot = 'bg-emerald-500';
            $svcLabel = 'Online';
            $topBarClass = 'from-emerald-500/80 via-emerald-400/60 to-transparent';
            $hoverBorderClass = 'hover:border-emerald-500/45 dark:hover:border-emerald-400/50';
            $hoverChevronClass = 'group-hover:text-emerald-600 dark:group-hover:text-emerald-400';
        } elseif ($svcSt === 'stand_by') {
            $svcDot = 'bg-amber-500';
            $svcLabel = 'Stand-by';
            $topBarClass = 'from-amber-500/80 via-amber-400/60 to-transparent';
            $hoverBorderClass = 'hover:border-amber-500/45 dark:hover:border-amber-400/50';
            $hoverChevronClass = 'group-hover:text-amber-600 dark:group-hover:text-amber-400';
        } else {
            $svcDot = 'bg-red-500';
            $svcLabel = 'Offline';
            $topBarClass = 'from-red-500/80 via-red-400/60 to-transparent';
            $hoverBorderClass = 'hover:border-red-500/45 dark:hover:border-red-400/50';
            $hoverChevronClass = 'group-hover:text-red-600 dark:group-hover:text-red-400';
        }
        $hz = \strtolower((string) $service->horizon_status);
        if ($hz === 'active' || $hz === 'running') {
            $hzDot = 'bg-emerald-500';
            $hzLabel = 'Horizon active';
        } else {
            $hzDot = 'bg-amber-500';
            $hzLabel = 'Horizon inactive';
        }
    @endphp
    <a
        href="{{ route('horizon.services.show', $service) }}"
        class="card group relative block overflow-hidden transition-colors {{ $hoverBorderClass }}"
        data-turbo-action="replace"
        data-stream-row-id="svc-h-{{ (int) $service->id }}"
    >
        <div
            class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $topBarClass }}"
            aria-hidden="true"
        ></div>
        <div class="relative flex items-start justify-between gap-2 p-4">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ $service->name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block size-1.5 shrink-0 rounded-full {{ $svcDot }}" title="{{ $svcLabel }}" aria-hidden="true"></span>
                        {{ $svcLabel }}
                    </span>
                    <span class="mt-0.5 flex items-center gap-1">
                        <span class="inline-block size-1.5 shrink-0 rounded-full {{ $hzDot }}" title="{{ $hzLabel }}" aria-hidden="true"></span>
                        <span class="truncate">{{ $hzLabel }}</span>
                    </span>
                </p>
            </div>
            <x-heroicon-o-chevron-right class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 {{ $hoverChevronClass }}" />
        </div>
    </a>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <x-empty-state
            title="No services registered"
            description="Register a Horizon service to see health here."
        >
            <x-slot name="icon">
                <x-heroicon-o-server-stack class="empty-state-icon" />
            </x-slot>
            <x-button type="button" class="mt-3 h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                Go to Services
            </x-button>
        </x-empty-state>
    </div>
@endforelse
