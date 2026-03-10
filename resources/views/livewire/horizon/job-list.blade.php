<div>
    <livewire:horizon.job-table :show-list-actions="true" />

    @if($showRetryModal)
        @php
            $retryModalSelectableIds = \array_values(\array_column(\array_filter($retryModalFailedJobsList, function ($j) { return ! empty($j['has_service'] ?? false); }), 'id'));
        @endphp
        @teleport('body')
            <x-confirm-modal
                title="Retry jobs"
                size="xl"
                cancelText="Cancel"
                cancelAction="closeRetryModal"
                backdropAction="closeRetryModal"
            >
                <div
                    class="flex min-h-0 flex-1 flex-col overflow-hidden p-2"
                    x-data
                    x-init="
                        if (!Alpine.store('retryModalSelection')) {
                            Alpine.store('retryModalSelection', {
                                selectedIds: [],
                                selectableIds: [],
                                toggle(id) {
                                    const i = this.selectedIds.indexOf(id);
                                    if (i >= 0) this.selectedIds.splice(i, 1);
                                    else this.selectedIds.push(id);
                                },
                                selectAll() { this.selectedIds = [...this.selectableIds]; },
                                clear() { this.selectedIds = []; }
                            });
                        }
                        Alpine.store('retryModalSelection').selectableIds = @js($retryModalSelectableIds);
                        Alpine.store('retryModalSelection').selectedIds = [];
                    "
                >
                    <p class="text-sm text-muted-foreground mb-3">Select failed jobs to retry. Filter by service, search or date range.</p>
                    <div class="mb-3 flex shrink-0 flex-wrap items-end gap-3">
                        <div class="space-y-2">
                            <x-input-label for="retry-modal-service">Service</x-input-label>
                            <x-select id="retry-modal-service" wire:model.blur="retryModalServiceFilter" class="w-44">
                                <option value="">All services</option>
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="retry-modal-search">Search</x-input-label>
                            <x-text-input id="retry-modal-search" type="text" wire:model.blur="retryModalSearch" placeholder="Queue, job or UUID" class="w-48" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="retry-modal-date-from">From</x-input-label>
                            <x-input-date id="retry-modal-date-from" wire:model.blur="retryModalDateFrom" class="w-40" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label for="retry-modal-date-to">To</x-input-label>
                            <x-input-date id="retry-modal-date-to" wire:model.blur="retryModalDateTo" class="w-40" />
                        </div>
                        <div class="flex items-end gap-2">
                            <x-button type="button" variant="outline" class="h-9 text-sm" @click="$store.retryModalSelection.selectAll()">Select all</x-button>
                            <x-button type="button" variant="ghost" class="h-9 text-sm" @click="$store.retryModalSelection.clear()">Clear selection</x-button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto rounded-md border border-border" style="max-height: min(45vh, 320px);">
                        <table class="min-w-full text-sm">
                            <thead class="bg-muted/50 sticky top-0">
                                <tr class="border-b border-border">
                                    <th class="w-10 px-3 py-2 text-left"></th>
                                    <th class="px-3 py-2 text-left font-medium text-foreground">Service</th>
                                    <th class="px-3 py-2 text-left font-medium text-foreground">Queue</th>
                                    <th class="px-3 py-2 text-left font-medium text-foreground">Job</th>
                                    <th class="px-3 py-2 text-left font-medium text-foreground">Failed at</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse($retryModalFailedJobsList as $job)
                                    <tr class="hover:bg-muted/30">
                                        <td class="px-3 py-2">
                                            @if($job['has_service'] ?? false)
                                                <x-checkbox
                                                    ::checked="$store.retryModalSelection.selectedIds.includes({{ $job['id'] }})"
                                                    @change="$store.retryModalSelection.toggle({{ $job['id'] }})"
                                                    aria-label="Select job {{ $job['id'] }}"
                                                />
                                            @else
                                                <span class="text-muted-foreground" title="No service">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-muted-foreground">{{ $job['service_name'] ?? '–' }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-muted-foreground">{{ $job['queue'] ?? '–' }}</td>
                                        <td class="px-3 py-2 text-muted-foreground truncate max-w-[200px]" title="{{ $job['name'] ?? '' }}">{{ $job['name'] ?? '–' }}</td>
                                        <td class="px-3 py-2 text-muted-foreground" data-datetime="{{ $job['failed_at_iso'] ?? '' }}">{{ $job['failed_at_formatted'] ?? '–' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-muted-foreground">No failed jobs to show.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <x-slot:footer>
                    <div class="flex w-full flex-wrap items-center justify-end gap-2" x-data="{ retrying: false }">
                        <x-button type="button" variant="ghost" wire:click="closeRetryModal">Cancel</x-button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-1 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50"
                            :disabled="$store.retryModalSelection.selectedIds.length === 0 || retrying"
                            @click="retrying = true; $wire.retrySelectedInModal($store.retryModalSelection.selectedIds)"
                        >
                            <span x-show="!retrying" x-text="'Retry selected (' + $store.retryModalSelection.selectedIds.length + ')'"></span>
                            <span x-show="retrying" class="inline-flex items-center gap-1">
                                <x-loader class="size-4" />
                                Retrying…
                            </span>
                        </button>
                    </div>
                </x-slot:footer>
            </x-confirm-modal>
        @endteleport
    @endif

    @if($showCleanModal)
        @teleport('body')
            <x-confirm-modal
                :title="$cleanStep === 1 ? 'Clean jobs' : 'Confirm'"
                :message="$cleanStep === 1 ? null : 'Are you sure you want to permanently delete ' . $this->cleanCount . ' job(s)? This cannot be undone.'"
                :variant="$cleanStep === 1 ? 'warning' : 'danger'"
                backdropVariant="default"
                size="md"
                :confirmText="$cleanStep === 1 ? 'Clean ' . $this->cleanCount . ' jobs' : 'Delete'"
                confirmAction="{{ $cleanStep === 1 ? 'confirmCleanJobs' : 'runCleanJobs' }}"
                cancelAction="closeCleanModal"
                backdropAction="closeCleanModal"
            >
                @if($cleanStep === 1)
                    <p class="text-xs text-muted-foreground mb-3">Choose filters. Matching jobs will be permanently deleted.</p>
                    <div class="space-y-2">
                        <div class="space-y-2">
                            <x-input-label>Service</x-input-label>
                            <x-select wire:model.live="cleanServiceId" class="w-full">
                                <option value="">All</option>
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="space-y-2">
                            <x-input-label>Status</x-input-label>
                            <x-select wire:model.live="cleanStatus" class="w-full" :options="['' => 'All', 'processed' => 'Processed', 'failed' => 'Failed', 'processing' => 'Processing']" />
                        </div>
                        <div class="space-y-2">
                            <x-input-label>Job type</x-input-label>
                            <x-text-input type="text" wire:model.live.debounce.200ms="cleanJobType" placeholder="e.g. App\Jobs\SendEmail" class="w-full" />
                        </div>
                        <p class="text-sm text-muted-foreground">{{ $this->cleanCount }} job(s) match.</p>
                    </div>
                @endif
            </x-confirm-modal>
        @endteleport
    @endif
</div>

@script
<script>
    window.addEventListener('horizonhub-refresh', () => {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
