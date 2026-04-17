<span id="dashboard-services-health-dot" class="inline-flex shrink-0 size-2.5 rounded-full {{ $servicesHealthDotClass ?? 'bg-slate-400' }}" title="Aggregate service status" aria-hidden="true"></span>
<span id="dashboard-value-services-online" class="text-2xl font-semibold text-foreground">
    {{ (int) ($servicesOnlineCount ?? 0) }} / {{ (int) ($servicesTotal ?? 0) }}
</span>
