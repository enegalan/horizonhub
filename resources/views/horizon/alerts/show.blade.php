@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonAlertDetail ? window.horizonAlertDetail({
            initialDeliveryLog: @js($initialDeliveryLogPayload ?? null),
        }) : {}"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-alert-detail"
    >
        <p class="mb-3 text-xs text-muted-foreground" data-alert-detail-breadcrumb>
            <a href="{{ route('horizon.alerts.index') }}" class="link" data-turbo-action="replace">Alerts</a> /
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
                            $serviceIds = isset($ruleConfig['service_ids']) && \is_array($ruleConfig['service_ids']) ? $ruleConfig['service_ids'] : [];
                            $serviceNames = [];
                            foreach ($serviceIds as $serviceId) {
                                foreach ($services as $s) {
                                    if ((int) $s->id === (int) $serviceId) {
                                        $serviceNames[] = (string) $s->name;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <dd class="mt-0.5 text-foreground">
                            @if(\count($serviceNames) > 0)
                                {{ \implode(', ', $serviceNames) }}
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
                        $jobPatternsShow = isset($threshold['job_patterns']) && \is_array($threshold['job_patterns']) ? $threshold['job_patterns'] : [];
                        $queuePatternsShow = isset($threshold['queue_patterns']) && \is_array($threshold['queue_patterns']) ? $threshold['queue_patterns'] : [];
                    @endphp
                    @if(\count($jobPatternsShow) > 0)
                        <div class="sm:col-span-2">
                            <dt class="label-muted">Job</dt>
                            <dd class="mt-0.5 text-foreground font-mono text-xs whitespace-pre-wrap">{{ \implode("\n", \array_map('strval', $jobPatternsShow)) }}</dd>
                        </div>
                    @endif
                    @if(\count($queuePatternsShow) > 0)
                        <div class="sm:col-span-2">
                            <dt class="label-muted">Queue patterns</dt>
                            <dd class="mt-0.5 text-foreground font-mono text-xs whitespace-pre-wrap">{{ \implode("\n", \array_map('strval', $queuePatternsShow)) }}</dd>
                        </div>
                    @endif
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

            <x-turbo::frame id="alert-logs">
            <div class="card mb-4">
                <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                    <form method="GET" action="{{ route('horizon.alerts.show', $alert) }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="alert-logs">
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
                                    <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? \config('horizonhub.jobs_per_page')) === $size)>{{ $size }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    </form>
                </div>
                <x-table
                    resizable-key="horizon-alert-detail-logs"
                    column-ids="sent_at,service,events,status,actions"
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="sent_at">Sent at</th>
                            <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                            <th class="table-header px-4 py-2.5" data-column-id="events">Events</th>
                            <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                            <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                        </tr>
                    </x-slot:head>
                            @forelse($logs as $log)
                                <tr class="transition-colors hover:bg-muted/30">
                                    <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="sent_at">{{ $log->sent_at->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="service">
                                        @if($log->service)
                                            <a href="{{ route('horizon.services.show', $log->service) }}" class="link" data-turbo-action="replace">{{ $log->service->name }}</a>
                                        @else
                                            –
                                        @endif
                                    </td>
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
                                            @php
                                                /** @var \App\Models\AlertLog $log */
                                                $payload = \App\Support\Alerts\AlertDeliveryLogPresenter::payloadFromLog($log);
                                            @endphp
                                            <x-button
                                                variant="outline"
                                                type="button"
                                                class="inline-flex items-center justify-center h-8 min-h-8 p-2 rounded-md"
                                                aria-label="View delivery log"
                                                title="View delivery log"
                                                @click='openDeliveryLogModal({{ \Illuminate\Support\Js::from($payload) }})'
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
                </x-table>
                <div class="border-t border-border px-4 py-2">
                    <x-pagination :paginator="$logs" />
                </div>
            </div>
            </x-turbo::frame>

            <template x-if="deliveryLogModalMounted">
                <div>
                    <x-confirm-modal
                        title="Delivery log"
                        size="lg"
                        x-data
                        x-show="showDeliveryLogModal"
                        x-on:close-modal.window="closeDeliveryLogModal()"
                    >
                        <dl class="space-y-2 text-sm" x-show="deliveryLog">
                            <div>
                                <dt class="label-muted">Sent at</dt>
                                <dd class="text-foreground" x-text="deliveryLog ? deliveryLog.sent_at : '–'"></dd>
                            </div>
                            <div>
                                <dt class="label-muted">Service</dt>
                                <dd class="text-foreground" x-text="deliveryLog ? deliveryLog.service_name : '–'"></dd>
                            </div>
                            <div>
                                <dt class="label-muted">Events</dt>
                                <dd class="text-foreground font-mono text-xs" x-text="deliveryLog ? deliveryLog.events_text : '–'"></dd>
                            </div>
                            <div x-show="deliveryLog && deliveryLog.events_count > 1">
                                <dt class="label-muted">Events in this delivery</dt>
                                <dd class="text-foreground" x-text="deliveryLog ? deliveryLog.events_count : 0"></dd>
                            </div>
                            <div x-show="deliveryLog && deliveryLog.job_items && deliveryLog.job_items.length > 0">
                                <dt class="label-muted">Job IDs (grouped)</dt>
                                <dd class="text-foreground flex flex-wrap gap-1">
                                    <template x-for="jobItem in (deliveryLog ? (deliveryLog.job_items || []) : [])" :key="jobItem.id">
                                        <span class="inline-flex items-center rounded border border-border px-1.5 py-0.5 text-[11px] font-mono text-muted-foreground bg-muted/40">
                                            <span x-text="jobItem.id"></span>
                                            <span class="mx-1 text-xs text-foreground">x</span>
                                            <span x-text="jobItem.count"></span>
                                        </span>
                                    </template>
                                </dd>
                                <dd class="text-xs text-muted-foreground mt-1" x-show="deliveryLog && deliveryLog.job_ids_more > 0" x-text="'+' + deliveryLog.job_ids_more + ' more job types'"></dd>
                            </div>
                            <div>
                                <dt class="label-muted">Status</dt>
                                <dd>
                                    <span class="badge-success" x-show="deliveryLog && deliveryLog.status === 'sent'">sent</span>
                                    <span class="badge-danger" x-show="deliveryLog && deliveryLog.status !== 'sent'">failed</span>
                                </dd>
                            </div>
                            <div x-show="deliveryLog && deliveryLog.status === 'failed' && deliveryLog.failure_message">
                                <dt class="label-muted">Failure reason</dt>
                                <dd class="mt-1 rounded-md border border-border bg-muted/30 px-3 py-2 font-mono text-xs text-foreground whitespace-pre-wrap break-words" x-text="deliveryLog ? deliveryLog.failure_message : ''"></dd>
                            </div>
                        </dl>
                        <x-slot:footer>
                            <div class="flex w-full flex-wrap items-center justify-end gap-2">
                                <x-button type="button" variant="ghost" @click="closeDeliveryLogModal()">Close</x-button>
                            </div>
                        </x-slot:footer>
                    </x-confirm-modal>
                </div>
            </template>
        </div>
    </div>
@endsection
