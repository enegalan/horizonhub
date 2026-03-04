<div>
    @if($editingServiceId)
        @teleport('body')
            <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4">
                @include('components.backdrop', array('variant' => 'default', 'wireClick' => 'cancelEdit'))
                <div class="relative z-10 card w-full max-w-md p-4 bg-card">
                <h2 class="text-section-title text-foreground mb-3">Edit service</h2>
                <form wire:submit="updateService" class="space-y-3">
                    <div class="space-y-1.5">
                        <x-input-label class="text-[11px] font-medium text-muted-foreground">Name</x-input-label>
                        <x-text-input type="text" wire:model="editName" class="w-full" />
                        @error('editName') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-1.5">
                        <x-input-label class="text-[11px] font-medium text-muted-foreground">Base URL</x-input-label>
                        <x-text-input type="url" wire:model="editBaseUrl" class="w-full" />
                        @error('editBaseUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
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
                                <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </x-button>
                        <x-button variant="ghost" type="button" wire:click="cancelEdit" class="h-9 text-sm">Cancel</x-button>
                    </div>
                </form>
            </div>
            </div>
        @endteleport
    @endif

    @if($newApiKey)
        <div class="card mb-4 border-amber-500/30 bg-amber-500/5">
            <div class="px-4 py-3">
                <p class="text-xs font-medium text-amber-800 dark:text-amber-200">New service API key — copy now, it won't be shown again.</p>
                <code class="mt-2 block rounded-md border border-border bg-muted/50 px-3 py-2 font-mono text-xs break-all text-foreground">{{ $newApiKey }}</code>
                <x-button variant="ghost" type="button" wire:click="$set('newApiKey', null)" class="mt-2 h-8 text-xs">Dismiss</x-button>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="px-4 py-3">
            <h2 class="text-section-title text-foreground mb-3">Register service</h2>
            <form wire:submit="save" class="space-y-3 max-w-sm">
                <div class="space-y-1.5">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Name</x-input-label>
                    <x-text-input type="text" wire:model="name" placeholder="my-service" class="w-full" />
                    @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                </div>
                <div class="space-y-1.5">
                    <x-input-label class="text-[11px] font-medium text-muted-foreground">Base URL</x-input-label>
                    <x-text-input type="url" wire:model="baseUrl" placeholder="https://my-service.example.com" class="w-full" />
                    @error('baseUrl') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
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
                        <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
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
                                @else
                                    <span class="badge-danger">offline</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ $service->horizon_jobs_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="failed">{{ $service->horizon_failed_jobs_count ?? 0 }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="last_seen">{{ $service->last_seen_at?->diffForHumans() ?? '–' }}</td>
                            <td class="px-4 py-2.5" data-column-id="actions">
                                <div class="flex items-center gap-2">
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
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008z"/></svg>
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
    window.addEventListener('horizon-hub-refresh', () => {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
