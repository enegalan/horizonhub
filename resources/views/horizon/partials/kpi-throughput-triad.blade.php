@php
    $idPrefix ??= 'metrics';
    $defer ??= false;
    $failedLabel ??= 'Failed jobs (past 7 days)';
    $minute = $jobsPastMinute ?? '—';
    $hour = $jobsPastHour ?? '—';
    $failed = $failedPastSevenDays ?? '—';
@endphp
<x-stat-card label="Jobs past minute" tone="emerald" value-id="{{ $idPrefix }}-value-jobs-minute">
    @if(! empty($defer))
        <x-skeleton.text class="h-8 w-16" />
    @else
        {{ $minute }}
    @endif
</x-stat-card>
<x-stat-card label="Jobs past hour" tone="sky" value-id="{{ $idPrefix }}-value-jobs-hour">
    @if(! empty($defer))
        <x-skeleton.text class="h-8 w-16" />
    @else
        {{ $hour }}
    @endif
</x-stat-card>
<x-stat-card label="{{ $failedLabel }}" tone="rose" value-id="{{ $idPrefix }}-value-failed-seven">
    @if(! empty($defer))
        <x-skeleton.text class="h-8 w-16" />
    @else
        {{ $failed }}
    @endif
</x-stat-card>
