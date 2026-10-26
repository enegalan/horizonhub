@php
    $formatCount = static fn (int $value, bool $mayBeTruncated): string => \number_format($value) . ($mayBeTruncated ? '+' : '');
@endphp

<x-stat-card label="Pending jobs" tone="amber" :value="number_format($pendingJobs)" />
<x-stat-card label="Processes" tone="emerald" :value="number_format($processes)" />
<x-stat-card label="Processed" tone="sky" :value="$formatCount($processedJobs, $processedMayBeTruncated)" />
<x-stat-card label="Failed" tone="rose" :value="$formatCount($failedJobs, $failedMayBeTruncated)" />
<x-stat-card label="Silenced" tone="rose">
    @if($silencedJobs === null)
        <span title="Unavailable for one or more services">—</span>
    @else
        <span>{{ number_format($silencedJobs) }}</span>
    @endif
</x-stat-card>
