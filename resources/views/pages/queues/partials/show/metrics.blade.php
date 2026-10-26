@php
    /** @var array<string, mixed>|null $metrics */
    $formatSparkline = static function (array $values): string {
        $values = \array_map(static fn ($value): float => \is_numeric($value) ? (float) $value : 0.0, $values);

        if (\count($values) < 2) {
            return '';
        }

        $max = \max($values) ?: 1.0;
        $count = \count($values);
        $points = [];

        foreach ($values as $index => $value) {
            $points[] = \number_format(($index / ($count - 1)) * 100, 2, '.', '')
                . ','
                . \number_format(28 - (($value / $max) * 26), 2, '.', '');
        }

        return \implode(' ', $points);
    };
@endphp

@if($metrics === null)
    <div class="flex min-h-24 items-center justify-center rounded-lg border border-dashed border-border bg-muted/10 px-4 py-6 text-center">
        <div>
            <p class="text-sm font-medium text-foreground">No metrics history available</p>
            <p class="mt-1 text-xs text-muted-foreground">Horizon has not recorded metrics for this queue yet.</p>
        </div>
    </div>
@else
    @php
        $cards = [
            [
                'label' => 'Throughput',
                'key' => 'throughput',
                'value' => $metrics['throughput'] !== null
                    ? \number_format((float) $metrics['throughput'], 2) . ' jobs/min'
                    : '—',
                'tone' => 'text-sky-500',
            ],
            [
                'label' => 'Runtime',
                'key' => 'runtime',
                'value' => $metrics['runtime'] !== null
                    ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $metrics['runtime'])
                    : '—',
                'tone' => 'text-violet-500',
            ],
            [
                'label' => 'Wait',
                'key' => 'wait',
                'value' => $metrics['wait'] !== null
                    ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $metrics['wait'])
                    : '—',
                'tone' => 'text-emerald-500',
            ],
        ];
    @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach($cards as $card)
            @php $polyline = $formatSparkline($metrics['series'][$card['key']] ?? []); @endphp
            <div class="rounded-lg border border-border/70 bg-muted/10 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $card['label'] }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-foreground">{{ $card['value'] }}</p>
                @if($polyline !== '')
                    <svg class="mt-2 h-8 w-full {{ $card['tone'] }}" viewBox="0 0 100 30" preserveAspectRatio="none" role="img" aria-label="{{ $card['label'] }} history">
                        <polyline points="{{ $polyline }}" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                    </svg>
                @else
                    <div class="mt-2 h-8 rounded bg-muted/30"></div>
                @endif
            </div>
        @endforeach
    </div>
@endif
