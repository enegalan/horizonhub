@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Service>|iterable $services */
@endphp
@forelse($services as $service)
    <a
        href="{{ route('horizon.services.show', $service) }}"
        class="card block p-3 transition-colors hover:bg-muted/30"
        data-turbo-action="replace"
        data-stream-row-id="svc-h-{{ (int) $service->id }}"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-foreground">{{ $service->name }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    @php
                        $svcSt = \strtolower((string) ($service->status ?? ''));
                        if ($svcSt === 'online') {
                            $svcDot = 'bg-emerald-500';
                            $svcLabel = 'Online';
                        } elseif ($svcSt === 'offline') {
                            $svcDot = 'bg-red-500';
                            $svcLabel = 'Offline';
                        } elseif ($svcSt === 'stand_by') {
                            $svcDot = 'bg-amber-500';
                            $svcLabel = 'Stand-by';
                        } else {
                            $svcDot = 'bg-slate-400';
                            $svcLabel = 'Unknown';
                        }
                        $hz = isset($service->horizon_status) ? \strtolower((string) $service->horizon_status) : '';
                        if ($hz === 'active' || $hz === 'running') {
                            $hzDot = 'bg-emerald-500';
                            $hzLabel = 'Horizon active';
                        } elseif ($hz === 'inactive') {
                            $hzDot = 'bg-amber-500';
                            $hzLabel = 'Horizon inactive';
                        } else {
                            $hzDot = 'bg-slate-400';
                            $hzLabel = $hz !== '' ? 'Horizon '.(string) $service->horizon_status : 'Horizon unknown';
                        }
                    @endphp
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
            <x-heroicon-o-chevron-right class="size-4 shrink-0 text-muted-foreground" />
        </div>
    </a>
@empty
    <div class="card p-6 sm:col-span-2 xl:col-span-3">
        <div class="empty-state">
            <x-heroicon-o-server-stack class="empty-state-icon" />
            <p class="empty-state-title">No services registered</p>
            <p class="empty-state-description">Register a Horizon service to see health here.</p>
            <x-button type="button" class="mt-3 h-9 text-sm" onclick="window.location.href='{{ route('horizon.services.index') }}'">
                Go to Services
            </x-button>
        </div>
    </div>
@endforelse
