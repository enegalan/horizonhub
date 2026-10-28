@php
    /** @var array<string, mixed> $summary */
    $maxWaitValue = ($summary['maxWaitSeconds'] ?? null) !== null
        ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $summary['maxWaitSeconds'])
        : '—';
    $maxWaitDetail = $summary['maxWaitQueue'] ?: 'No queue is waiting';
@endphp

<div class="min-w-0 px-5 py-4 sm:px-6">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Max wait</p>
    <p class="mt-1 break-all text-lg font-semibold tabular-nums text-foreground">{{ $maxWaitValue }}</p>
    <p class="mt-1 truncate text-xs text-muted-foreground" title="{{ $maxWaitDetail }}">{{ $maxWaitDetail }}</p>
</div>
