@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Service>|\App\Models\Service[] $services */
    $services = collect($services ?? [])->sortBy(function (\App\Models\Service $service): int {
        if (! $service->enabled) {
            return 3;
        }

        return match ($service->status) {
            \App\Enums\ServiceStatus::Online => 2,
            \App\Enums\ServiceStatus::StandBy => 1,
            default => 0,
        };
    })->values();
@endphp
@forelse($services as $service)
    @php
        $svcSt = $service->status;
        $isOnline = $svcSt === \App\Enums\ServiceStatus::Online;
        $isStandBy = $svcSt === \App\Enums\ServiceStatus::StandBy;
        $isEnabled = $service->enabled;

        if ($isEnabled && $isOnline) {
            $svcDot = 'bg-emerald-500';
            $svcBadge = 'badge-success';
            $svcLabel = 'Online';
        } elseif ($isEnabled && $isStandBy) {
            $svcDot = 'bg-amber-500';
            $svcBadge = 'badge-warning';
            $svcLabel = 'Stand-by';
        } elseif (! $isEnabled) {
            $svcDot = 'bg-gray-500';
            $svcBadge = 'badge-muted';
            $svcLabel = 'Disabled';
        } else {
            $svcDot = 'bg-red-500';
            $svcBadge = 'badge-danger';
            $svcLabel = $service->hasTimeoutAdvice() ? 'Timed Out' : 'Offline';
        }

        $hzStatus = \App\Enums\HorizonStatus::tryFrom(\strtolower((string) $service->horizon_status));
        $hzActive = $hzStatus?->isActive() === true;
        $hzLabel = $hzActive ? 'Horizon active' : 'Horizon inactive';
        $hzBadge = $hzActive ? 'badge-success' : 'badge-warning';
    @endphp
    <a
        href="{{ route('horizon.services.show', $service) }}"
        class="group flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-muted/40 sm:px-5"
        data-turbo-action="replace"
        data-stream-row-id="svc-h-{{ (int) $service->id }}"
    >
        <span class="inline-flex size-2 shrink-0 rounded-full {{ $svcDot }}" title="{{ $svcLabel }}" aria-hidden="true"></span>
        <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground" title="{{ $service->name }}">{{ $service->name }}</span>
        <span class="{{ $svcBadge }} shrink-0">{{ $svcLabel }}</span>
        <span class="{{ $hzBadge }} hidden shrink-0 sm:inline-flex">{{ $hzLabel }}</span>
        <x-icons.chevron-right class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
    </a>
@empty
    <div class="p-8">
        <x-empty-state
            title="No services registered"
            description="Register a Horizon service to see health here."
        >
            <x-slot name="icon">
                <x-icons.server-stack class="empty-state-icon" />
            </x-slot>
            <x-button type="button" class="mt-3 h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                Go to Services
            </x-button>
        </x-empty-state>
    </div>
@endforelse
