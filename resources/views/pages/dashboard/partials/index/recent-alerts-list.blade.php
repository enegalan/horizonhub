@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\AlertLog>|\App\Models\AlertLog[] $recentAlertLogs */
    $recentAlertLogs ??= collect();
    $defer = (bool) ($defer ?? false);
@endphp
@if($defer)
    @for($i = 0; $i < 3; $i++)
        <div class="animate-pulse px-4 py-3 sm:px-5">
            <div class="flex items-center gap-3">
                <div class="h-3 w-32 rounded bg-muted"></div>
                <div class="ml-auto h-3 w-16 rounded bg-muted"></div>
            </div>
            <div class="mt-2 h-3 w-24 rounded bg-muted"></div>
        </div>
    @endfor
@else
    @forelse($recentAlertLogs as $log)
        @php
            $isFailed = $log->status === \App\Enums\AlertLogStatus::Failed;
            $statusBadge = $isFailed ? 'badge-danger' : 'badge-success';
            $statusLabel = $log->status?->label() ?? ($log->status?->value ?? '–');
            $href = $log->alert
                ? route('horizon.alerts.show', $log->alert)
                : route('horizon.alerts.index');
        @endphp
        <a
            href="{{ $href }}"
            class="group flex flex-wrap items-center gap-2 px-4 py-2.5 transition-colors hover:bg-muted/40 sm:gap-3 sm:px-5"
            data-turbo-action="replace"
            data-stream-row-id="al-{{ (int) $log->id }}"
        >
            <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground" title="{{ $log->alert?->name ?? ('#'.$log->alert_id) }}">
                {{ $log->alert?->name ?? '#'.$log->alert_id }}
            </span>
            @if($log->service)
                <span class="max-w-28 truncate text-xs text-muted-foreground sm:max-w-36" title="{{ $log->service->name }}">{{ $log->service->name }}</span>
            @endif
            <span class="{{ $statusBadge }} shrink-0">{{ $statusLabel }}</span>
            <span class="shrink-0 text-xs text-muted-foreground">
                @if($log->sent_at)
                    {{ $log->sent_at->diffForHumans() }}
                @else
                    –
                @endif
            </span>
            <x-icons.chevron-right class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground" />
        </a>
    @empty
        <div class="p-8">
            <x-empty-state
                title="No recent alert activity"
                description="Triggered alerts will appear here."
            >
                <x-slot name="icon">
                    <x-icons.bell class="empty-state-icon" />
                </x-slot>
            </x-empty-state>
        </div>
    @endforelse
@endif
