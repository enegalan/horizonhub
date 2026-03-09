<div>
    <div class="card">
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-2">
                <x-input-label>Service</x-input-label>
                <x-select wire:model.live="serviceFilter" class="w-44">
                    <option value="">All</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                    @endforeach
                </x-select>
            </div>
            <div class="space-y-2">
                <x-input-label>Status</x-input-label>
                <x-select wire:model.live="statusFilter" class="w-32" :options="['' => 'All', 'processed' => 'Processed', 'failed' => 'Failed', 'processing' => 'Processing']" />
            </div>
            <div class="space-y-2">
                <x-input-label>Search</x-input-label>
                <x-text-input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Queue, job or UUID"
                    class="w-56"
                />
            </div>
            <div class="ml-auto flex items-center gap-2">
                <div
                    wire:loading.flex
                    wire:target="serviceFilter,statusFilter,search"
                    class="items-center gap-1 text-xs text-muted-foreground"
                >
                    <x-loader class="size-3" />
                    <span>Loading…</span>
                </div>
                <x-button type="button" variant="outline" wire:click="openCleanModal" class="h-9 text-sm">Clean jobs</x-button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-job-list" data-column-ids="service,queue,job,status,attempts,queued_at,processed,failed_at,runtime,actions">
                <thead wire:ignore>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="attempts">Attempts</th>
                        <th class="table-header px-4 py-2.5" data-column-id="queued_at">Queued at</th>
                        <th class="table-header px-4 py-2.5" data-column-id="processed">Processed</th>
                        <th class="table-header px-4 py-2.5" data-column-id="failed_at">Failed at</th>
                        <th class="table-header px-4 py-2.5" data-column-id="runtime">Runtime</th>
                        <th class="table-header px-4 py-2.5" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($jobs as $job)
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground" data-column-id="queue">{{ $job->queue }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->job_uuid }}</td>
                            <td class="px-4 py-2.5" data-column-id="status">
                                @php $jobStatus = $job->status ?? '–'; @endphp
                                @if($jobStatus === 'failed')
                                    <span class="badge-danger">{{ $jobStatus }}</span>
                                @elseif($jobStatus === 'processed')
                                    <span class="badge-success">{{ $jobStatus }}</span>
                                @else
                                    <span class="badge-muted">{{ $jobStatus }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="attempts">{{ $job->attempts ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="queued_at" data-datetime="{{ $job->queued_at?->toIso8601String() ?? '' }}">{{ $job->queued_at ? '…' : '–' }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="processed" data-datetime="{{ $job->processed_at?->toIso8601String() ?? '' }}">{{ $job->processed_at ? '…' : '–' }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="failed_at" data-datetime="{{ $job->failed_at?->toIso8601String() ?? '' }}">{{ $job->failed_at ? '…' : '–' }}</td>
                            <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="runtime">
                                {{ $job->getFormattedRuntime() ?? '–' }}
                            </td>
                            <td class="px-4 py-2.5" data-column-id="actions">
                                <div class="flex items-center gap-2">
                                    <x-button
                                        variant="outline"
                                        type="button"
                                        onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->id]) }}'"
                                        class="h-8 min-h-8 p-2 rounded-md"
                                        aria-label="View"
                                        title="View"
                                    >
                                        <x-heroicon-o-eye class="size-4" />
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" data-column-id="service">
                                <div class="empty-state">
                                    <x-heroicon-o-document-text class="empty-state-icon" />
                                    <p class="empty-state-title">No jobs yet</p>
                                    @if(count($services) === 0)
                                    <p class="empty-state-description">Register a service and push events from the Agent to see jobs here.</p>
                                        <x-button
                                            type="button"
                                            class="text-xs"
                                            onclick="window.location.href='{{ route('horizon.services.index') }}'"
                                        >
                                            Register a service
                                        </x-button>
                                    @else
                                        <p class="empty-state-description">No jobs match the current filters.</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-2">
            <x-pagination :paginator="$jobs" />
        </div>
    </div>

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
