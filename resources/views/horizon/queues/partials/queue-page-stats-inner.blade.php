<div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Queues</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ number_format((int) ($queueCount ?? 0)) }}</p>
</div>
<div class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-amber-800 dark:text-amber-300">Pending jobs</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ number_format((int) ($totalJobs ?? 0)) }}</p>
</div>
