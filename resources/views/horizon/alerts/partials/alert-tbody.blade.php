@php
    /** @var \App\Models\Alert $alert */
@endphp
@forelse($alerts as $alert)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="alt-{{ (int) $alert->id }}">
        <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name">
            <a href="{{ route('horizon.alerts.show', $alert) }}" class="link" data-turbo-action="replace">{{ $alert->name ?: ('Alert #' . $alert->id) }}</a>
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="service">
            @php
                $serviceLabels = $alert->scopedServiceNames();
            @endphp
            @if(\count($serviceLabels) > 0)
                {{ \count($serviceLabels) === 1 ? $serviceLabels[0] : \implode(', ', $serviceLabels) }}
            @else
                All
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm font-mono text-muted-foreground" data-column-id="rule_type">{{ $alert->rule_type }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="queue">
            @php
                $qps = isset($alert->threshold['queue_patterns']) && is_array($alert->threshold['queue_patterns']) ? $alert->threshold['queue_patterns'] : [];
            @endphp
            @if(\count($qps) > 1)
                {{ $qps[0] }} (+{{ \count($qps) - 1 }})
            @elseif(\count($qps) === 1)
                {{ $qps[0] }}
            @else
                {{ $alert->queue ?? '–' }}
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job_type">
            @php
                $jps = isset($alert->threshold['job_patterns']) && is_array($alert->threshold['job_patterns']) ? $alert->threshold['job_patterns'] : [];
            @endphp
            @if(\count($jps) > 1)
                {{ \Illuminate\Support\Str::limit((string) $jps[0], 24) }} (+{{ \count($jps) - 1 }})
            @elseif(\count($jps) === 1)
                {{ \Illuminate\Support\Str::limit((string) $jps[0], 32) }}
            @else
                {{ $alert->job_type ? \Illuminate\Support\Str::limit($alert->job_type, 32) : '–' }}
            @endif
        </td>
        <td class="px-4 py-2.5" data-column-id="enabled">
            @if($alert->enabled)
                <span class="badge-success">On</span>
            @else
                <span class="badge-danger">Off</span>
            @endif
        </td>
        <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="last_triggered">
            @if($alert->alert_logs_max_sent_at)
                {{ \Carbon\Carbon::parse($alert->alert_logs_max_sent_at)->diffForHumans() }}
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5" data-column-id="actions" data-stream-preserve-client>
            <div class="flex items-center gap-2">
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 p-2 alert-evaluate-btn"
                    disabled="{{ !$alert->enabled }}"
                    aria-label="Evaluate alert"
                    title="Evaluate alert"
                    data-alert-evaluate-button="1"
                    data-alert-id="{{ (int) $alert->id }}"
                    data-alert-evaluate-url="{{ route('horizon.alerts.evaluate', $alert) }}"
                    data-alert-evaluate-initial-disabled="{{ $alert->enabled ? '0' : '1' }}"
                >
                    <span class="inline-flex items-center justify-center">
                        <x-heroicon-o-arrow-path class="size-4 alert-evaluate-btn-icon" />
                        <x-heroicon-o-arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                    </span>
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 p-2"
                    aria-label="Edit"
                    title="Edit"
                    onclick="window.location.href='{{ route('horizon.alerts.edit', $alert) }}'"
                >
                    <x-heroicon-o-pencil-square class="size-4" />
                </x-button>
                <form method="POST" action="{{ route('horizon.alerts.destroy', $alert) }}" onsubmit="return confirm('Delete alert {{ $alert->name ?: ('#' . $alert->id) }}?');">
                    @csrf
                    @method('DELETE')
                    <x-button
                        variant="ghost"
                        type="submit"
                        class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                        aria-label="Delete"
                        title="Delete"
                    >
                        <x-heroicon-o-trash class="size-4" />
                    </x-button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" data-column-id="name">
            <div class="empty-state">
                <x-heroicon-o-bell class="empty-state-icon" />
                <p class="empty-state-title">No alerts</p>
                <p class="empty-state-description">Create an alert rule to get notified when jobs fail, queues block, or workers go offline.</p>
                <x-button
                    type="button"
                    class="mt-3 h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
                >
                    New alert
                </x-button>
            </div>
        </td>
    </tr>
@endforelse
