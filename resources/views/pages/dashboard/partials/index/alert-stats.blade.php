<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Total Alerts</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($alertStats['total']) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Enabled</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($alertStats['enabled']) }}</p>
</div>
