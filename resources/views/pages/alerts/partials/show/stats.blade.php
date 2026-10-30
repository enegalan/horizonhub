@if(!empty($defer))
    @include('pages.alerts.partials.show.skeletons.stats')
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="label-muted">Sent (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((int) ($sent24h ?? 0)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Failed (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((int) ($failed24h ?? 0)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Total (7 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((int) ($total7d ?? 0)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Total (30 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((int) ($total30d ?? 0)) }}</p>
        </div>
    </div>
@endif
