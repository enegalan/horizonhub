@php
    $totalAlerts = (int) ($alertStats['totalAlerts'] ?? 0);
    $enabledAlerts = (int) ($alertStats['enabledAlerts'] ?? 0);
    $failedDeliveries = (int) ($alertStats['failedDeliveries'] ?? 0);
@endphp

<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Total Alerts</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($totalAlerts) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Enabled</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($enabledAlerts) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Failed Deliveries</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($failedDeliveries) }}</p>
</div>
