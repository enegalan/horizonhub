@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonAlertDetail ? window.horizonAlertDetail() : {}"
        x-init="typeof init === 'function' ? init() : null"
        data-horizon-alert-detail-root="1"
    >
        <p class="mb-3 text-xs text-muted-foreground" data-alert-detail-breadcrumb>
            <a href="{{ route('horizon.alerts.index') }}" class="link">Alerts</a> /
            <span class="text-foreground">{{ $alertName }}</span>
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6" data-alert-detail-stats>
            <div class="card p-4">
                <h3 class="label-muted">Sent (24h)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['sent'])) }}</p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Failed (24h)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart24h']['failed'])) }}</p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Total (7 days)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart7d']['sent']) + array_sum($chartData['chart7d']['failed'])) }}</p>
            </div>
            <div class="card p-4">
                <h3 class="label-muted">Total (30 days)</h3>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format(array_sum($chartData['chart30d']['sent']) + array_sum($chartData['chart30d']['failed'])) }}</p>
            </div>
        </div>

        @if(!empty($ruleConfig))
            <div class="card p-4 mb-4" data-alert-detail-rule>
                <h3 class="text-section-title text-foreground mb-3">Alert rule</h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="label-muted">Type</dt>
                        <dd class="mt-0.5 text-foreground font-mono text-xs">{{ $ruleConfig['rule_type'] ?? 'unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="label-muted">Service scope</dt>
                        @php
                            $serviceId = $ruleConfig['service_id'] ?? null;
                            $serviceName = null;
                            if ($serviceId !== null) {
                                foreach ($services as $s) {
                                    if ((int) $s->id === (int) $serviceId) {
                                        $serviceName = $s->name;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <dd class="mt-0.5 text-foreground">
                            @if($serviceName)
                                {{ $serviceName }}
                            @else
                                Any service
                            @endif
                        </dd>
                    </div>
                    @if(!empty($ruleConfig['queue']))
                        <div>
                            <dt class="label-muted">Queue</dt>
                            <dd class="mt-0.5 text-foreground font-mono text-xs">{{ $ruleConfig['queue'] }}</dd>
                        </div>
                    @endif
                    @if(!empty($ruleConfig['job_type']))
                        <div>
                            <dt class="label-muted">Job type</dt>
                            <dd class="mt-0.5 text-foreground font-mono text-xs">{{ $ruleConfig['job_type'] }}</dd>
                        </div>
                    @endif
                    @php
                        $threshold = $ruleConfig['threshold'] ?? [];
                    @endphp
                    @if(!empty($threshold))
                        <div>
                            <dt class="label-muted">Threshold</dt>
                            <dd class="mt-0.5 text-foreground text-xs">
                                @if(isset($threshold['count']))
                                    Failures: {{ (int) $threshold['count'] }}
                                @endif
                                @if(isset($threshold['seconds']))
                                    <span class="mr-1"></span>Seconds: {{ (float) $threshold['seconds'] }}
                                @endif
                                @if(isset($threshold['minutes']))
                                    <span class="mr-1"></span>Window: {{ (int) $threshold['minutes'] }} min
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="grid gap-4 mb-6" data-alert-detail-charts>
            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Sends in the last 24h (by hour)</h3>
                <div id="alert-detail-chart-24h" class="h-56"></div>
            </div>
            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Sends in the last 7 days (by day)</h3>
                <div id="alert-detail-chart-7d" class="h-56"></div>
            </div>
            <div class="card p-4">
                <h3 class="text-section-title text-foreground mb-2">Sends in the last 30 days (by day)</h3>
                <div id="alert-detail-chart-30d" class="h-56"></div>
            </div>
        </div>

        <div data-alert-detail-after-charts>
            <script type="application/json" id="alert-detail-chart-data">@json($chartData)</script>

            <div class="card mb-4">
                <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                    <form method="GET" action="{{ route('horizon.alerts.show', $alert) }}" class="flex flex-wrap items-end gap-3">
                        <div class="space-y-2">
                            <x-input-label for="serviceFilter">Service</x-input-label>
                            <x-select id="serviceFilter" name="service_id" class="w-44" onchange="this.form.submit()">
                                <option value="">All</option>
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}" @selected(($filters['service_id'] ?? '') !== '' && (int) ($filters['service_id'] ?? 0) === (int) $s->id)>{{ $s->name }} ({{ $s->status }})</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="statusFilter">Status</x-input-label>
                            <x-select id="statusFilter" name="status" class="w-36" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="sent" @selected(($filters['status'] ?? '') === 'sent')>Sent</option>
                                <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Failed</option>
                            </x-select>
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="perPage">Per page</x-input-label>
                            <x-select id="perPage" name="per_page" class="w-24" onchange="this.form.submit()">
                                @foreach([10,20,50] as $size)
                                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? \config('horizonhub.alerts_per_page')) === $size)>{{ $size }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full" data-resizable-table="horizon-alert-detail-logs" data-column-ids="sent_at,service,events,status,actions">
                        <thead>
                            <tr class="border-b border-border bg-muted/50">
                                <th class="table-header px-4 py-2.5" data-column-id="sent_at">Sent at</th>
                                <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                                <th class="table-header px-4 py-2.5" data-column-id="events">Events</th>
                                <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                                <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($logs as $log)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="sent_at">{{ $log->sent_at->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="service">{{ $log->service?->name ?? '–' }}</td>
                                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="events">
                                        @php $tc = (int) ($log->trigger_count ?? 0); @endphp
                                        @if($tc >= 1)
                                            <span class="text-muted-foreground">{{ $tc }} {{ $tc === 1 ? 'event' : 'events' }}</span>
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
                                            <x-button
                                                variant="outline"
                                                type="button"
                                                class="inline-flex items-center justify-center h-8 min-h-8 p-2 rounded-md"
                                                aria-label="View delivery log"
                                                title="View delivery log"
                                                onclick="window.location.href='{{ route('horizon.alerts.show', ['alert' => $alert->id, 'log' => $log->id] + request()->except('page')) }}'"
                                            >
                                                <x-heroicon-o-document-text class="size-4" />
                                            </x-button>
                                            @if($log->status === 'failed')
                                                <form method="POST" action="{{ route('horizon.alerts.logs.retry', $log) }}">
                                                    @csrf
                                                    <x-button
                                                        variant="outline"
                                                        type="submit"
                                                        class="h-8 min-h-8 p-2 rounded-md"
                                                        aria-label="Retry delivery"
                                                        title="Retry delivery"
                                                    >
                                                        <x-heroicon-o-arrow-path class="size-4" />
                                                    </x-button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" data-column-id="sent_at">
                                        <div class="empty-state">
                                            <x-heroicon-o-bell class="empty-state-icon" />
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
                    <x-pagination :paginator="$logs" />
                </div>
            </div>

            @if($selectedLog)
                @php
                    $sl = $selectedLog;
                    $triggerCount = (int) ($sl->trigger_count ?? 0);
                    if ($triggerCount < 1) $triggerCount = 1;
                    $jobUuids = is_array($sl->job_uuids ?? null) ? $sl->job_uuids : [];
                    $totals = [];
                    foreach ($jobUuids as $uuid) {
                        $key = (string) $uuid;
                        $totals[$key] = ($totals[$key] ?? 0) + 1;
                    }
                    $jobIdsLimited = \array_slice(\array_keys($totals), 0, \config('horizonhub.alerts_per_page'));
                    $jobIdsMore = \count($totals) > \config('horizonhub.alerts_per_page') ? \count($totals) - \config('horizonhub.alerts_per_page') : 0;
                @endphp
                @teleport('body')
                    <div id="alert-log-modal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4" role="dialog" aria-modal="true" aria-labelledby="alert-log-modal-title">
                        @include('components.backdrop', ['variant' => 'default'])
                        <div class="relative z-10 card w-full max-w-lg p-4 bg-card">
                            <h2 id="alert-log-modal-title" class="text-section-title text-foreground mb-3">Delivery log</h2>
                            <dl class="space-y-2 text-sm">
                                <div>
                                    <dt class="label-muted">Sent at</dt>
                                    <dd class="text-foreground">{{ $sl->sent_at->format('Y-m-d H:i:s') }}</dd>
                                </div>
                                <div>
                                    <dt class="label-muted">Service</dt>
                                    <dd class="text-foreground">{{ $sl->service?->name ?? '–' }}</dd>
                                </div>
                                <div>
                                    <dt class="label-muted">Events</dt>
                                    <dd class="text-foreground font-mono text-xs">{{ $triggerCount === 1 ? '1 event' : $triggerCount . ' events' }}</dd>
                                </div>
                                @if($triggerCount > 1)
                                    <div>
                                        <dt class="label-muted">Events in this delivery</dt>
                                        <dd class="text-foreground">{{ $triggerCount }}</dd>
                                    </div>
                                @endif
                                @if(!empty($jobIds))
                                    <div>
                                        <dt class="label-muted">Job IDs (grouped)</dt>
                                        <dd class="text-foreground flex flex-wrap gap-1">
                                            @foreach($jobIdsLimited as $jid)
                                                <span class="inline-flex items-center rounded border border-border px-1.5 py-0.5 text-[11px] font-mono text-muted-foreground bg-muted/40">
                                                    {{ $jid }}
                                                    <span class="mx-1 text-xs text-foreground">×</span>
                                                    {{ $totals[$jid] }}
                                                </span>
                                            @endforeach
                                        </dd>
                                        @if($jobIdsMore > 0)
                                            <dd class="text-xs text-muted-foreground mt-1">+{{ $jobIdsMore }} more job types</dd>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <dt class="label-muted">Status</dt>
                                    <dd>
                                        @if($sl->status === 'sent')
                                            <span class="badge-success">sent</span>
                                        @else
                                            <span class="badge-danger">failed</span>
                                        @endif
                                    </dd>
                                </div>
                                @if($sl->status === 'failed' && !empty($sl->failure_message))
                                    <div>
                                        <dt class="label-muted">Failure reason</dt>
                                        <dd class="mt-1 rounded-md border border-border bg-muted/30 px-3 py-2 font-mono text-xs text-foreground whitespace-pre-wrap break-words">
                                            {{ $sl->failure_message }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                            <div class="pt-4">
                                <div class="flex items-center justify-end gap-3">
                                    <x-button
                                        variant="ghost"
                                        type="button"
                                        class="h-9 text-sm rounded-md px-3 text-muted-foreground hover:bg-muted/50 hover:text-foreground"
                                        onclick="window.location.href='{{ route('horizon.alerts.show', ['alert' => $alert->id] + request()->except('log')) }}'"
                                    >
                                        Close
                                    </x-button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endteleport
            @endif
        </div>
    </div>
@endsection
