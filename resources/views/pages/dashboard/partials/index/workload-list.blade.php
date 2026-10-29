@php
    /** @var list<array<string, mixed>> $workloadRows */
    $workloadRows ??= [];
    $defer = (bool) ($defer ?? false);

    if (! $defer && $workloadRows !== []) {
        \usort($workloadRows, static function (array $left, array $right): int {
            return ((int) ($right['jobs'] ?? 0)) <=> ((int) ($left['jobs'] ?? 0));
        });
    }
@endphp
@if($defer)
    @for($i = 0; $i < 3; $i++)
        <div class="animate-pulse flex items-center gap-3 px-4 py-2.5 sm:px-5">
            <div class="h-3 w-28 rounded bg-muted"></div>
            <div class="ml-auto h-3 w-40 rounded bg-muted"></div>
        </div>
    @endfor
@else
    @forelse($workloadRows as $row)
        @php
            $queue = (string) ($row['queue'] ?? '');
            $jobs = (int) ($row['jobs'] ?? 0);
            $processes = isset($row['processes']) && $row['processes'] !== '' && $row['processes'] !== null
                ? (int) $row['processes']
                : null;
            $serviceId = (int) ($row['service_id'] ?? 0);
            $serviceName = (string) ($row['service'] ?? '');
            $waitSeconds = isset($row['wait']) && $row['wait'] !== '' && $row['wait'] !== null
                ? (float) $row['wait']
                : null;
            $href = $queue !== ''
                ? route('horizon.queues.show', ['queue' => $queue])
                : route('horizon.queues.index');
            $rowKey = 'wl-top-'.$serviceId.'-'.rawurlencode($queue);
        @endphp
        <a
            href="{{ $href }}"
            class="group flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 transition-colors hover:bg-muted/40 sm:px-5"
            data-turbo-action="replace"
            data-stream-row-id="{{ $rowKey }}"
        >
            <span class="min-w-0 flex-1">
                <span class="block truncate font-mono text-sm font-medium text-foreground" title="{{ $queue }}">
                    {{ $queue !== '' ? $queue : '—' }}
                </span>
                @if($serviceName !== '')
                    <span class="block truncate text-xs text-muted-foreground" title="{{ $serviceName }}">{{ $serviceName }}</span>
                @endif
            </span>

            <span class="inline-flex shrink-0 items-center divide-x divide-border overflow-hidden rounded-md border border-border/70 bg-muted/20 text-xs">
                <span class="px-2 py-1 tabular-nums">
                    <span class="text-muted-foreground">jobs</span>
                    <span class="ml-1 font-semibold text-foreground">{{ number_format($jobs) }}</span>
                </span>
                <span class="px-2 py-1 tabular-nums">
                    <span class="text-muted-foreground">proc</span>
                    <span class="ml-1 font-semibold text-foreground">
                        {{ $processes !== null ? number_format($processes) : '—' }}
                    </span>
                </span>
                <span class="px-2 py-1 tabular-nums">
                    <span class="text-muted-foreground">wait</span>
                    <span class="ml-1 font-semibold text-foreground">
                        @if($waitSeconds !== null)
                            <span data-wait-seconds="{{ $waitSeconds }}">
                                {{ \App\Support\Jobs\JobRuntime::getFormattedRuntime($waitSeconds) }}
                            </span>
                        @else
                            —
                        @endif
                    </span>
                </span>
            </span>

            <x-icons.chevron-right class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
        </a>
    @empty
        <div class="p-8">
            <x-empty-state
                title="No queue workload"
                description="Queues will show here once work is pending across your services."
            >
                <x-slot name="icon">
                    <x-icons.queue-list class="empty-state-icon" />
                </x-slot>
            </x-empty-state>
        </div>
    @endforelse
@endif
