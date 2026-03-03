<div @horizon-hub-refresh.window="$wire.$refresh()">
    <div class="card mb-4">
        <div class="px-4 py-3 flex items-center justify-between">
            <h2 class="text-section-title text-foreground">Alert rules</h2>
            <a href="{{ route('horizon.alerts.create') }}" wire:navigate>
                <x-ui.button type="button" class="h-9 text-sm">New alert</x-ui.button>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full">
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
                                <a href="{{ route('horizon.alerts.show', $alert) }}" wire:navigate class="link">{{ $alert->name ?: ('Alert #' . $alert->id) }}</a>
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
                                    <a href="{{ route('horizon.alerts.edit', $alert) }}" wire:navigate>
                                        <x-ui.button variant="ghost" type="button" class="h-8 min-h-8 p-2" aria-label="Edit" title="Edit">
                                            <x-heroicon-o-pencil-square class="size-4" />
                                        </x-ui.button>
                                    </a>
                                    <x-ui.button variant="ghost" type="button" wire:click="confirmDeleteAlert({{ $alert->id }})" class="h-8 min-h-8 p-2 text-destructive hover:text-destructive" aria-label="Delete" title="Delete">
                                        <x-heroicon-o-trash class="size-4" />
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                                    <p class="empty-state-title">No alerts</p>
                                    <p class="empty-state-description">Create an alert rule to get notified when jobs fail, queues block, or workers go offline.</p>
                                    <a href="{{ route('horizon.alerts.create') }}" wire:navigate>
                                        <x-ui.button type="button" class="mt-3 h-9 text-sm">New alert</x-ui.button>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($confirmingAlertId)
        <x-ui.confirm-modal
            title="Delete alert"
            message="Are you sure you want to delete {{ $confirmingAlertName }}? This cannot be undone."
            variant="danger"
            size="sm"
            confirmText="Delete"
            cancelText="Cancel"
            confirmAction="performDeleteAlert"
            cancelAction="cancelDeleteAlert"
            backdropAction="cancelDeleteAlert"
        />
    @endif
</div>
