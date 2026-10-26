@php
    /** @var array<string, mixed> $summary */
    $cards = [
        [
            'label' => 'Max wait',
            'value' => ($summary['maxWaitSeconds'] ?? null) !== null
                ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $summary['maxWaitSeconds'])
                : '—',
            'detail' => $summary['maxWaitQueue'] ?: 'No queue is waiting',
            'box' => 'border-sky-500/20 bg-sky-500/5',
            'labelClass' => 'text-sky-700 dark:text-sky-300',
        ],
        [
            'label' => 'Longest runtime queue',
            'value' => $summary['queueWithMaxRuntime'] ?: '—',
            'detail' => 'Queue with the slowest recent job',
            'box' => 'border-violet-500/20 bg-violet-500/5',
            'labelClass' => 'text-violet-700 dark:text-violet-300',
        ],
        [
            'label' => 'Busiest queue',
            'value' => $summary['queueWithMaxThroughput'] ?: '—',
            'detail' => 'Queue with the highest throughput',
            'box' => 'border-emerald-500/20 bg-emerald-500/5',
            'labelClass' => 'text-emerald-700 dark:text-emerald-300',
        ],
    ];
@endphp

@foreach($cards as $card)
    <div class="rounded-lg border px-4 py-3 {{ $card['box'] }}">
        <p class="text-xs font-medium uppercase tracking-wide {{ $card['labelClass'] }}">{{ $card['label'] }}</p>
        <p class="mt-1 break-all text-2xl font-semibold tabular-nums text-foreground">{{ $card['value'] }}</p>
        <p class="mt-1 truncate text-xs text-muted-foreground" title="{{ $card['detail'] }}">{{ $card['detail'] }}</p>
    </div>
@endforeach
