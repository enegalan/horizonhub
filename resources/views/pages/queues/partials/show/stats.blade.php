@php
    $formatCount = static fn (int $value, bool $mayBeTruncated): string => \number_format($value) . ($mayBeTruncated ? '+' : '');
@endphp

<x-stat-card label="Pending jobs" :value="number_format($pendingJobs)" />
<x-stat-card label="Processes" :value="number_format($processes)" />
<x-stat-card label="Processed" :value="$formatCount($processedJobs, $processedMayBeTruncated)" />
<x-stat-card label="Failed" :value="$formatCount($failedJobs, $failedMayBeTruncated)" />
<x-stat-card label="Silenced">
    @if($silencedJobs === null)
        <span title="Unavailable for one or more services">—</span>
    @else
        <span>{{ number_format($silencedJobs) }}</span>
    @endif
</x-stat-card>
