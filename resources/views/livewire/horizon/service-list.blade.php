<div>
    @if($editingServiceId)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4">
                @include('components.backdrop', ['variant' => 'default', 'wireClick' => 'cancelEdit'])
                <div class="relative z-10 card w-full max-w-md p-4 bg-card">
                <h2 class="text-section-title text-foreground mb-3">Edit service</h2>
                <form wire:submit="updateService" class="space-y-3">
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" wire:model="editName" class="w-full" />
                        @error('editName') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" wire:model="editBaseUrl" class="w-full" />
                        @error('editBaseUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" wire:model="editPublicUrl" class="w-full" />
                        @error('editPublicUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            URL reachable from your browser.
                        </p>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <x-button
                            type="submit"
                            class="h-9 text-sm relative inline-flex items-center justify-center"
                            wire:loading.attr="disabled"
                            wire:target="updateService"
                        >
                            <span wire:loading.remove wire:target="updateService">
                                Save
                            </span>
                            <span wire:loading wire:target="updateService" class="inline-flex" aria-hidden="true">
                                <x-loader />
                            </span>
                        </x-button>
                        <x-button variant="ghost" type="button" wire:click="cancelEdit" class="h-9 text-sm">Cancel</x-button>
                    </div>
                </form>
            </div>
            </div>
        @endteleport
    @endif

    <div class="card mb-4">
        <div class="px-4 py-3">
            <h2 class="text-section-title text-foreground mb-3">Register service</h2>
            <form wire:submit="save" class="space-y-3 max-w-sm">
                <div class="space-y-2">
                    <x-input-label>Name</x-input-label>
                    <x-text-input type="text" wire:model="name" placeholder="my-service" class="w-full" />
                    @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-2">
                    <x-input-label>Base URL</x-input-label>
                    <x-text-input type="url" wire:model="baseUrl" placeholder="http://my-service" class="w-full" />
                    @error('baseUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        Internal URL used to obtain events from the service.
                    </p>
                </div>
                <div class="space-y-2">
                    <x-input-label>Public URL (optional)</x-input-label>
                    <x-text-input type="url" wire:model="publicUrl" placeholder="http://my-service:8080" class="w-full" />
                    @error('publicUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    <p class="text-xs text-muted-foreground">
                        URL reachable from your browser.
                    </p>
                </div>
                <x-button
                    type="submit"
                    class="h-9 text-sm relative inline-flex items-center justify-center"
                    wire:loading.attr="disabled"
                    wire:target="save"
                >
                    <span wire:loading.remove wire:target="save">
                        Register
                    </span>
                    <span wire:loading wire:target="save" class="inline-flex" aria-hidden="true">
                        <x-loader />
                    </span>
                </x-button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-service-list" data-column-ids="name,base_url,status,jobs,failed,last_seen,actions">
                <thead wire:ignore>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                        <th class="table-header px-4 py-2.5" data-column-id="base_url">Base URL</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                        <th class="table-header px-4 py-2.5" data-column-id="failed">Failed</th>
                        <th class="table-header px-4 py-2.5" data-column-id="last_seen">Last seen</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($services as $service)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name"><a href="{{ route('horizon.services.show', $service) }}" wire:navigate class="link">{{ $service->name }}</a></td>
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="base_url">{{ $service->base_url ?? '–' }}</td>
                            <td class="px-4 py-2.5" data-column-id="status">
                                @if($service->status === 'online')
                                    <span class="badge-success">online</span>
                                @elseif($service->status === 'stand_by')
                                    <span class="badge-warning">stand by</span>
                                @else
                                    <span class="badge-danger">offline</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ $service->horizon_jobs_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="failed">{{ $service->horizon_failed_jobs_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="last_seen">{{ $service->last_seen_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                            <td class="px-4 py-2.5" data-column-id="actions">
                                <div class="flex items-center gap-2">
                                    @php
                                        $dashboardBase = $service->public_url ?: $service->base_url;
                                    @endphp
                                    @if($dashboardBase)
                                        <x-button
                                            variant="ghost"
                                            as="a"
                                            onclick="window.open('{{ rtrim($dashboardBase, '/') . \config('horizonhub.horizon.dashboard_path') }}', '_blank')"
                                            class="h-8 min-h-8 p-2"
                                            aria-label="Open Horizon dashboard"
                                            title="Open Horizon dashboard"
                                        >
                                            <x-heroicon-o-window class="size-4" />
                                        </x-button>
                                    @endif
                                    <x-button
                                        variant="ghost"
                                        type="button"
                                        wire:click="testConnection({{ $service->id }})"
                                        class="h-8 min-h-8 p-2"
                                        wire:loading.attr="disabled"
                                        wire:target="testConnection"
                                        aria-label="Test connection"
                                        title="Test connection"
                                    >
                                        <span wire:loading.remove wire:target="testConnection">
                                            <x-heroicon-o-signal class="size-4" />
                                        </span>
                                        <span wire:loading wire:target="testConnection" class="inline-flex" aria-hidden="true">
                                            <x-loader />
                                        </span>
                                    </x-button>
                                    <x-button variant="ghost" type="button" wire:click="openEdit({{ $service->id }})" class="h-8 min-h-8 p-2" aria-label="Edit" title="Edit">
                                        <x-heroicon-o-pencil-square class="size-4" />
                                    </x-button>
                                    <x-button variant="ghost" type="button" wire:click="confirmDeleteService({{ $service->id }})" class="h-8 min-h-8 p-2 text-destructive hover:text-destructive" aria-label="Delete" title="Delete">
                                        <x-heroicon-o-trash class="size-4" />
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" data-column-id="name">
                                <div class="empty-state">
                                    <x-heroicon-o-server-stack class="empty-state-icon" />
                                    <p class="empty-state-title">No services</p>
                                    <p class="empty-state-description">Register a service above to connect your Horizon instance.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($confirmingServiceId)
        <x-confirm-modal
            title="Delete service"
            message="{{ $confirmingServiceMessage }}"
            variant="danger"
            size="sm"
            confirmText="Delete"
            cancelText="Cancel"
            confirmAction="performDeleteService"
            cancelAction="cancelDeleteService"
            backdropAction="cancelDeleteService"
        />
    @endif
</div>

@script
<script>
    window.addEventListener('horizonhub-refresh', () => {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
