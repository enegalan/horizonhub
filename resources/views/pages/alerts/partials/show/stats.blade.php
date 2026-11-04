<div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @if(!empty($defer))
        <x-skeleton.metric-columns :columns="4" />
    @else
        <x-stat-card label="Sent (24h)" :value="number_format((int) ($sent24h ?? 0))" />
        <x-stat-card label="Failed (24h)" :value="number_format((int) ($failed24h ?? 0))" />
        <x-stat-card label="Total (7 days)" :value="number_format((int) ($total7d ?? 0))" />
        <x-stat-card label="Total (30 days)" :value="number_format((int) ($total30d ?? 0))" />
    @endif
</div>