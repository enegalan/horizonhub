@extends('layouts.app')

@section('content')
    <div
        class="card mb-4"
        x-data="window.horizonAlertsList ? window.horizonAlertsList() : {}"
        x-init="typeof init === 'function' && init()"
    >
        <div class="px-4 py-3 flex items-center justify-between">
            <h2 class="text-section-title text-foreground">Alert rules</h2>
            <x-button
                type="button"
                class="h-9 text-sm"
                onclick="window.location.href='{{ route('horizon.alerts.create') }}'"
            >
                New alert
            </x-button>
        </div>
        @if(session('status'))
            <div class="px-4 pb-3 text-xs text-muted-foreground">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-alerts-list">
                <thead>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5">Name</th>
                        <th class="table-header px-4 py-2.5">Service</th>
                        <th class="table-header px-4 py-2.5">Rule type</th>
                        <th class="table-header px-4 py-2.5">Queue</th>
                        <th class="table-header px-4 py-2.5">Job type</th>
                        <th class="table-header px-4 py-2.5">Enabled</th>
                        <th class="table-header px-4 py-2.5">Last triggered</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($alerts as $alert)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm font-medium">
                                <a href="{{ route('horizon.alerts.show', $alert) }}" class="link">{{ $alert->name ?: ('Alert #' . $alert->id) }}</a>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground">
                                {{ $alert->service_id ? $alert->service->name : 'All' }}
                            </td>
                            <td class="px-4 py-2.5 text-sm font-mono text-muted-foreground">{{ $alert->rule_type }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground">{{ $alert->queue ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground">{{ $alert->job_type ?? '–' }}</td>
                            <td class="px-4 py-2.5">
                                @if($alert->enabled)
                                    <span class="badge-success">On</span>
                                @else
                                    <span class="badge-danger">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground">
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
                            <td colspan="8">
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
                </tbody>
            </table>
        </div>
    </div>
@endsection
