@php
    /** @var array<string, mixed> $summary */
    $cards = [
        [
            'label' => 'Max wait',
            'value' => ($summary['maxWaitSeconds'] ?? null) !== null
                ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $summary['maxWaitSeconds'])
                : '—',
            'detail' => $summary['maxWaitQueue'] ?: 'No queue is waiting',
        ],
        [
            'label' => 'Longest runtime queue',
            'value' => $summary['queueWithMaxRuntime'] ?: '—',
            'detail' => 'Queue with the slowest recent job',
        ],
        [
            'label' => 'Busiest queue',
            'value' => $summary['queueWithMaxThroughput'] ?: '—',
            'detail' => 'Queue with the highest throughput',
        ],
    ];
@endphp

@foreach($cards as $card)
    <div class="min-w-0 px-5 py-4 sm:px-6">
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $card['label'] }}</p>
        <p class="mt-1 break-all text-lg font-semibold tabular-nums text-foreground">{{ $card['value'] }}</p>
        <p class="mt-1 truncate text-xs text-muted-foreground" title="{{ $card['detail'] }}">{{ $card['detail'] }}</p>
    </div>
@endforeach
