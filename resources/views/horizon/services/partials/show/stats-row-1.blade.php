@include('horizon.partials.kpi-throughput-triad', [
    'idPrefix' => 'service-show',
    'defer' => false,
    'jobsPastMinute' => number_format($jobsPastMinute),
    'jobsPastHour' => number_format($jobsPastHour),
    'failedPastSevenDays' => number_format($failedPastSevenDays),
    'failedLabel' => 'Failed (past 7 days)',
])
@php
    /** @var \App\Enums\HorizonStatus|null $hzStatus */
    $hzStatus = \App\Enums\HorizonStatus::tryFrom(\strtolower((string) $horizonStatus));
    if ($hzStatus?->isActive() === true) {
        $horizonStatusColor = 'bg-emerald-500';
        $horizonStatusLabel = 'Active';
    } elseif ($hzStatus === \App\Enums\HorizonStatus::Inactive) {
        $horizonStatusColor = 'bg-amber-500';
        $horizonStatusLabel = 'Inactive';
    } else {
        $horizonStatusColor = 'bg-slate-400';
        $horizonStatusLabel = ! empty($horizonStatus) ? (string) $horizonStatus : 'Unknown';
    }
@endphp
<x-stat-card label="Horizon status" tone="{{ $hzStatus?->isActive() === true ? 'emerald' : ($hzStatus === \App\Enums\HorizonStatus::Inactive ? 'amber' : 'neutral') }}">
    <span
        class="inline-flex shrink-0 size-2.5 rounded-full {{ $horizonStatusColor }}"
        title="Horizon {{ $horizonStatusLabel }}"
        aria-label="Horizon {{ $horizonStatusLabel }}"
    ></span>
    <span>{{ $horizonStatusLabel }}</span>
</x-stat-card>
