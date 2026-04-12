<div class="card p-4">
    <h3 class="label-muted">Jobs past minute</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastMinute) }}</p>
</div>
<div class="card p-4">
    <h3 class="label-muted">Jobs past hour</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastHour) }}</p>
</div>
<div class="card p-4">
    <h3 class="label-muted">Failed (past 7 days)</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($failedPastSevenDays) }}</p>
</div>
<div class="card p-4">
    @php
        $hs = \strtolower((string) $horizonStatus);
        if ($hs === 'active' || $hs === 'running') {
            $horizonStatusColor = 'bg-emerald-500';
            $horizonStatusLabel = 'Active';
        } elseif ($hs === 'inactive') {
            $horizonStatusColor = 'bg-amber-500';
            $horizonStatusLabel = 'Inactive';
        } else {
            $horizonStatusColor = 'bg-slate-400';
            $horizonStatusLabel = $horizonStatus !== null && $horizonStatus !== '' ? (string) $horizonStatus : 'Unknown';
        }
    @endphp
    <h3 class="label-muted">Status</h3>
    <div class="mt-1 flex items-center gap-2">
        <span
            class="inline-flex shrink-0 size-2.5 rounded-full {{ $horizonStatusColor }}"
            title="Horizon {{ $horizonStatusLabel }}"
            aria-label="Horizon {{ $horizonStatusLabel }}"
        ></span>
        <p class="text-2xl font-semibold text-foreground">
            {{ $horizonStatusLabel }}
        </p>
    </div>
</div>
