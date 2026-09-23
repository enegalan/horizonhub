@php
    $servicesCount = (int) ($servicesCount ?? 0);
    $onlineCount = (int) ($onlineCount ?? 0);
    $anyOffline = (int) ($offlineCount ?? 0) > 0;
    $anyStandBy = (int) ($standByCount ?? 0) > 0;
    $servicesHealthDotClass = $servicesCount === 0 ?
        'bg-slate-400' : ($anyOffline ?
            'bg-orange-500' : ($anyStandBy ?
                'bg-amber-500' : 'bg-emerald-500'
            )
        );
@endphp
<span id="dashboard-services-health-dot" class="inline-flex shrink-0 size-2.5 rounded-full {{ $servicesHealthDotClass }}" title="Aggregate service status" aria-hidden="true"></span>
<span id="dashboard-value-services-online" class="text-2xl font-semibold text-foreground">
    {{ $onlineCount }} / {{ $servicesCount }}
</span>
