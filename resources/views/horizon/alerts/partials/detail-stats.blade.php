<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <div class="card p-4">
        <h3 class="label-muted">Sent (24h)</h3>
        <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['sent'])) }}</p>
    </div>
    <div class="card p-4">
        <h3 class="label-muted">Failed (24h)</h3>
        <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['failed'])) }}</p>
    </div>
    <div class="card p-4">
        <h3 class="label-muted">Total (7 days)</h3>
        <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart7d']['sent']) + array_sum($chartData['chart7d']['failed'])) }}</p>
    </div>
    <div class="card p-4">
        <h3 class="label-muted">Total (30 days)</h3>
        <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart30d']['sent']) + array_sum($chartData['chart30d']['failed'])) }}</p>
    </div>
</div>
