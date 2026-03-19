@extends('layouts.app')

@section('content')
    <div
        class="card mb-4"
        x-data="window.horizonAlertsList ? window.horizonAlertsList() : {}"
        x-init="typeof init === 'function' && init()"
    >
        <div class="px-4 py-3 flex items-center justify-between">
            <h2 class="text-section-title text-foreground">Alert rules</h2>
            <div class="flex items-center gap-2">
                @if($alerts->where('enabled', true)->count() > 0)
                    <x-button
                        variant="secondary"
                        type="button"
                        class="h-9 text-sm alert-evaluate-btn"
                        data-alert-evaluate-all-button="1"
                        data-alert-evaluate-all-url="{{ route('horizon.alerts.evaluate-all') }}"
                        data-alert-evaluate-all-status-url="{{ route('horizon.alerts.evaluations.status', ['evaluationId' => '__EVALUATION_ID__']) }}"
                    >
                        <span class="inline-flex items-center gap-2">
                            <x-heroicon-o-bell class="size-4 alert-evaluate-btn-icon" />
                            <x-heroicon-o-arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                            <span data-alert-evaluate-all-label>Evaluate all alerts</span>
                        </span>
                    </x-button>
                @endif
                <x-button
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
                >
                    New alert
                </x-button>
            </div>
        </div>
        @if(session('status'))
            <div class="px-4 pb-3 text-xs text-muted-foreground">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="card">
        <x-data-table
            resizable-key="horizon-alerts-list"
            column-ids="name,service,rule_type,queue,job_type,enabled,last_triggered,actions"
        >
            <x-slot:head>
                <tr class="border-b border-border bg-muted/50">
                    <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                    <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                    <th class="table-header px-4 py-2.5" data-column-id="rule_type">Rule type</th>
                    <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                    <th class="table-header px-4 py-2.5" data-column-id="job_type">Job type</th>
                    <th class="table-header px-4 py-2.5" data-column-id="enabled">Enabled</th>
                    <th class="table-header px-4 py-2.5" data-column-id="last_triggered">Last triggered</th>
                    <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                </tr>
            </x-slot:head>
                    @forelse($alerts as $alert)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name">
                                <a href="{{ route('horizon.alerts.show', $alert) }}" class="link">{{ $alert->name ?: ('Alert #' . $alert->id) }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="service">
                                {{ $alert->service_id ? $alert->service->name : 'All' }}
                            </td>
                            <td class="px-4 py-2.5 text-sm font-mono text-muted-foreground" data-column-id="rule_type">{{ $alert->rule_type }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="queue">{{ $alert->queue ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="job_type">{{ $alert->job_type ?? '–' }}</td>
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
                            <td class="px-4 py-2.5" data-column-id="actions">
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
        </x-data-table>
    </div>
@endsection
