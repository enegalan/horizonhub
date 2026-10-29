@php
    $totalJobs = (int) ($workloadStats['totalJobs'] ?? 0);
    $activeQueues = (int) ($workloadStats['activeQueues'] ?? 0);
    $queueCount = (int) ($workloadStats['queueCount'] ?? 0);
@endphp

<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Total Jobs</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($totalJobs) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Active Queues</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($activeQueues) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Queues</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($queueCount) }}</p>
</div>
