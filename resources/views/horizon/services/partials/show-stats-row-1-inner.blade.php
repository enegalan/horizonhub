<x-stat-card label="Jobs past minute" tone="emerald" :value="number_format($jobsPastMinute)" />
<x-stat-card label="Jobs past hour" tone="sky" :value="number_format($jobsPastHour)" />
<x-stat-card label="Failed (past 7 days)" tone="rose" :value="number_format($failedPastSevenDays)" />
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
        $horizonStatusLabel = !empty($horizonStatus) ? (string) $horizonStatus : 'Unknown';
    }
@endphp
<x-stat-card label="Horizon status" tone="{{ $hs === 'active' || $hs === 'running' ? 'emerald' : ($hs === 'inactive' ? 'amber' : 'neutral') }}">
    <span
        class="inline-flex shrink-0 size-2.5 rounded-full {{ $horizonStatusColor }}"
        title="Horizon {{ $horizonStatusLabel }}"
        aria-label="Horizon {{ $horizonStatusLabel }}"
    ></span>
    <span>{{ $horizonStatusLabel }}</span>
</x-stat-card>
