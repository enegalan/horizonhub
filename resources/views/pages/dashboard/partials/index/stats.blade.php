@php
    $pendingJobs = (int) ($stats['pendingJobs'] ?? 0);
    $pendingActiveQueues = (int) ($stats['pendingActiveQueues'] ?? 0);
    $pendingQueueCount = (int) ($stats['pendingQueueCount'] ?? 0);
    $failedJobs = (int) ($stats['failedJobs'] ?? 0);
    $failedPastHour = (int) ($stats['failedPastHour'] ?? 0);
    $failedPast24h = (int) ($stats['failedPast24h'] ?? 0);
    $completedJobs = (int) ($stats['completedJobs'] ?? 0);
    $jobsPerMinute = (int) ($stats['jobsPerMinute'] ?? 0);
    $silencedJobs = (int) ($stats['silencedJobs'] ?? 0);
    $batchesTotal = (int) ($stats['batchesTotal'] ?? 0);
    $batchesInProgress = (int) ($stats['batchesInProgress'] ?? 0);
    $batchesWithFailures = (int) ($stats['batchesWithFailures'] ?? 0);
@endphp

<div class="p-4 sm:p-5">
    <p class="text-sm text-muted-foreground">Pending jobs</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($pendingJobs) }}</p>
    <dl class="mt-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Active queues</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($pendingActiveQueues) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Queues</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($pendingQueueCount) }}</dd>
        </div>
    </dl>
</div>

<div class="p-4 sm:p-5">
    <p class="text-sm text-muted-foreground">Failed jobs</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($failedJobs) }}</p>
    <dl class="mt-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Past hour</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($failedPastHour) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Past 24 hours</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($failedPast24h) }}</dd>
        </div>
    </dl>
</div>

<div class="p-4 sm:p-5">
    <p class="text-sm text-muted-foreground">Completed jobs</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($completedJobs) }}</p>
    <dl class="mt-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Jobs Per Minute</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($jobsPerMinute) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">Silenced</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($silencedJobs) }}</dd>
        </div>
    </dl>
</div>

<div class="p-4 sm:p-5">
    <p class="text-sm text-muted-foreground">Recent batches</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($batchesTotal) }}</p>
    <dl class="mt-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">In progress</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($batchesInProgress) }}</dd>
        </div>
        <div class="flex items-baseline justify-between gap-3 border-b border-dotted border-border pb-1.5">
            <dt class="text-muted-foreground">With failures</dt>
            <dd class="tabular-nums font-medium text-foreground">{{ number_format($batchesWithFailures) }}</dd>
        </div>
    </dl>
</div>
