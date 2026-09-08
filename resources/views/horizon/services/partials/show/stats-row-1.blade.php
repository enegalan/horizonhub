@include('horizon.partials.kpi-throughput-triad', [
    'idPrefix' => 'service-show',
    'defer' => false,
    'jobsPastMinute' => number_format($jobsPastMinute),
    'jobsPastHour' => number_format($jobsPastHour),
    'failedPastSevenDays' => number_format($failedPastSevenDays),
    'failedLabel' => 'Failed (past 7 days)',
])
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
