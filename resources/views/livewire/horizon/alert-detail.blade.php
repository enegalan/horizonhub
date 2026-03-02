<div>
    <p class="mb-3 text-xs text-muted-foreground">
        <a href="{{ route('horizon.alerts.index') }}" wire:navigate class="link">Alerts</a> /
        <span class="text-foreground">{{ $alertName }}</span>
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Sent (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['sent'])) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Failed (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['failed'])) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Total (7 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart7d']['sent']) + array_sum($chartData['chart7d']['failed'])) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Total (30 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart30d']['sent']) + array_sum($chartData['chart30d']['failed'])) }}</p>
        </div>
    </div>

    <div class="grid gap-4 mb-6">
        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Sends in the last 24h (by hour)</h3>
            <div id="alert-detail-chart-24h" class="h-56"></div>
        </div>
        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Sends in the last 7 days (by day)</h3>
            <div id="alert-detail-chart-7d" class="h-56"></div>
        </div>
        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Sends in the last 30 days (by day)</h3>
            <div id="alert-detail-chart-30d" class="h-56"></div>
        </div>
    </div>

    <script type="application/json" id="alert-detail-chart-data">@json($chartData)</script>

    <div class="card mb-4">
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Status</x-input-label>
                <x-ui.select wire:model.live="statusFilter" class="w-36" :options="array('' => 'All', 'sent' => 'Sent', 'failed' => 'Failed')" />
            </div>
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Per page</x-input-label>
                <x-ui.select wire:model.live="perPage" class="w-24" :options="array(10 => '10', 20 => '20', 50 => '50')" />
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-alert-detail-logs" data-column-ids="sent_at,service,job,status,actions">
                <thead wire:ignore>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="sent_at">Sent at</th>
                        <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($logs as $log)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="sent_at">{{ $log->sent_at->format('Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="service">{{ $log->service?->name ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job">
                                @php $tc = $log->trigger_count ?? 1; @endphp
                                @if($log->job_id)
                                    <a href="{{ route('horizon.jobs.show', ['job' => $log->job_id]) }}" wire:navigate class="link font-mono text-xs">{{ $log->job_id }}</a>
                                    @if($tc > 1)
                                        <span class="text-muted-foreground"> ({{ $tc }} events)</span>
                                    @endif
                                @elseif($tc > 1)
                                    <span class="text-muted-foreground">{{ $tc }} events</span>
                                @else
                                    –
                                @endif
                            </td>
                            <td class="px-4 py-2.5" data-column-id="status">
                                @if($log->status === 'sent')
                                    <span class="badge-success">sent</span>
                                @else
                                    <span class="badge-danger">failed</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5" data-column-id="actions">
                                <div class="flex items-center gap-2">
                                    @if($log->job_id)
                                        <a href="{{ route('horizon.jobs.show', ['job' => $log->job_id]) }}" wire:navigate class="btn-secondary inline-flex items-center justify-center h-8 min-h-8 p-2 rounded-md" aria-label="View job" title="View job">
                                            <x-heroicon-o-eye class="size-4" />
                                        </a>
                                    @endif
                                    <button
                                        type="button"
                                        class="btn-secondary inline-flex items-center justify-center h-8 min-h-8 p-2 rounded-md"
                                        aria-label="View delivery log"
                                        title="View delivery log"
                                        data-alert-log="1"
                                        data-alert-sent-at="{{ $log->sent_at->format('Y-m-d H:i:s') }}"
                                        data-alert-service="{{ $log->service?->name }}"
                                        data-alert-job-id="{{ $log->job_id }}"
                                        data-alert-job-url="{{ $log->job_id ? route('horizon.jobs.show', ['job' => $log->job_id]) : '' }}"
                                        data-alert-trigger-count="{{ $log->trigger_count ?? 1 }}"
                                        data-alert-job-ids='@json($log->job_ids)'
                                        data-alert-status="{{ $log->status }}"
                                        data-alert-failure="{{ $log->failure_message }}"
                                    >
                                        <x-heroicon-o-document-text class="size-4" />
                                    </button>
                                    @if($log->status === 'failed')
                                        <x-ui.button
                                            variant="outline"
                                            type="button"
                                            wire:click="retryLog({{ $log->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="retryLog({{ $log->id }})"
                                            class="h-8 min-h-8 p-2 rounded-md relative"
                                            aria-label="Retry delivery"
                                            title="Retry delivery"
                                        >
                                            <span wire:loading.remove wire:target="retryLog({{ $log->id }})">
                                                <x-heroicon-o-arrow-path class="size-4" />
                                            </span>
                                            <span wire:loading wire:target="retryLog({{ $log->id }})" class="inline-flex" aria-hidden="true">
                                                <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </span>
                                        </x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" data-column-id="sent_at">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                    <p class="empty-state-title">No alert deliveries yet</p>
                                    <p class="empty-state-description">When this alert triggers, sent and failed notifications will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-2">
            <x-ui.pagination :paginator="$logs" />
        </div>
    </div>

    @teleport('body')
        <div id="alert-log-modal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 hidden" role="dialog" aria-modal="true" aria-labelledby="alert-log-modal-title">
            @include('components.ui.backdrop', array('variant' => 'default', 'extraAttrs' => 'data-alert-log-close'))
            <div class="relative z-10 card w-full max-w-lg p-4 bg-card">
            <h2 id="alert-log-modal-title" class="text-section-title text-foreground mb-3">Delivery log</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Sent at</dt>
                    <dd id="alert-log-sent-at" class="text-foreground"></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Service</dt>
                    <dd id="alert-log-service" class="text-foreground"></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Job</dt>
                    <dd id="alert-log-job" class="text-foreground font-mono text-xs"></dd>
                </div>
                <div id="alert-log-events-wrapper" class="hidden">
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Events in this delivery</dt>
                    <dd id="alert-log-events-count" class="text-foreground"></dd>
                </div>
                <div id="alert-log-job-ids-wrapper" class="hidden">
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Job IDs (grouped)</dt>
                    <dd id="alert-log-job-ids" class="text-foreground flex flex-wrap gap-1"></dd>
                    <dd id="alert-log-job-ids-more" class="text-[11px] text-muted-foreground mt-1"></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Status</dt>
                    <dd id="alert-log-status"></dd>
                </div>
                <div id="alert-log-failure-wrapper" class="hidden">
                    <dt class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Failure reason</dt>
                    <dd id="alert-log-failure-message" class="mt-1 rounded-md border border-border bg-muted/30 px-3 py-2 font-mono text-xs text-foreground whitespace-pre-wrap break-words"></dd>
                </div>
            </dl>
            <div class="pt-4">
                <div class="flex items-center justify-end gap-3">
                    <button type="button" data-alert-log-close class="h-9 text-sm rounded-md px-3 inline-flex items-center justify-center border-0 bg-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground">Close</button>
                </div>
            </div>
        </div>
        </div>
    @endteleport
</div>

@script
<script>
    window.addEventListener('horizon-hub-refresh', function () {
        if (window.horizonTableInteracting) return;
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
