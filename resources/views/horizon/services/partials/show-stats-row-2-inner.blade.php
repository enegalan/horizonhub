<div class="card p-4">
    <h3 class="label-muted">Total processes</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">
        {{ $totalProcesses !== null ? number_format($totalProcesses) : '–' }}
    </p>
</div>
<div class="card p-4">
    <h3 class="label-muted">Max wait time (s)</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">
        {{ $maxWaitTimeSeconds !== null ? number_format($maxWaitTimeSeconds, 2) : '–' }}
    </p>
</div>
<div class="card p-4">
    <h3 class="label-muted">Max runtime</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">
        {{ $queueWithMaxRuntime !== null ? $queueWithMaxRuntime : '–' }}
    </p>
</div>
<div class="card p-4">
    <h3 class="label-muted">Max throughput</h3>
    <p class="mt-1 text-2xl font-semibold text-foreground">
        {{ $queueWithMaxThroughput !== null ? $queueWithMaxThroughput : '–' }}
    </p>
</div>
