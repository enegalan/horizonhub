@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\AlertLog>|\App\Models\AlertLog[] $recentAlertLogs */
@endphp
@forelse($recentAlertLogs as $log)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="al-{{ (int) $log->id }}">
        <td class="px-4 py-2.5 text-sm" data-column-id="name">
            @if($log->alert)
                <a href="{{ route('horizon.alerts.show', $log->alert) }}" class="link" data-turbo-action="replace">{{ $log->alert->name ?: ('Alert #'.(int) $log->alert->id) }}</a>
            @else
                <span class="text-muted-foreground">#{{ (int) $log->alert_id }}</span>
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="service">
            @if($log->service)
                <a href="{{ route('horizon.services.show', $log->service) }}" class="link" data-turbo-action="replace">{{ $log->service->name }}</a>
            @else
                –
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="status">{{ $log->status ?? '–' }}</td>
        <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="sent">
            @if($log->sent_at)
                {{ $log->sent_at->diffForHumans() }}
            @else
                –
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4" class="px-4 py-6" data-column-id="name">
            <div class="empty-state py-4">
                <x-heroicon-o-bell class="empty-state-icon mx-auto size-10" />
                <p class="empty-state-title">No recent alert activity</p>
                <p class="empty-state-description">Triggered alerts will appear here.</p>
            </div>
        </td>
    </tr>
@endforelse
