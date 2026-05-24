@php
    /** @var array{chart24h: array{sent: array<int>, failed: array<int>}, chart7d: array{sent: array<int>, failed: array<int>}, chart30d: array{sent: array<int>, failed: array<int>}} $chartData */
    $chartData ??= [];
@endphp
@if(!empty($defer))
    @include('horizon.alerts.partials.show.skeletons.stats')
@else
    @php
        $chart24h = $chartData['chart24h'] ?? [];
        $chart7d = $chartData['chart7d'] ?? [];
        $chart30d = $chartData['chart30d'] ?? [];
        $sent24h = $chart24h['sent'] ?? [];
        $failed24h = $chart24h['failed'] ?? [];
        $sent7d = $chart7d['sent'] ?? [];
        $failed7d = $chart7d['failed'] ?? [];
        $sent30d = $chart30d['sent'] ?? [];
        $failed30d = $chart30d['failed'] ?? [];
    @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="label-muted">Sent (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($sent24h)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Failed (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($failed24h)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Total (7 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($sent7d) + array_sum($failed7d)) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="label-muted">Total (30 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($sent30d) + array_sum($failed30d)) }}</p>
        </div>
    </div>
@endif
