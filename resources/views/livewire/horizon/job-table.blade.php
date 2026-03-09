<div class="card">
    <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
        @if($showServiceColumn)
            <div class="space-y-2">
                <x-input-label>Service</x-input-label>
                <x-select wire:model.live="serviceFilter" class="w-44">
                    <option value="">All</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                    @endforeach
                </x-select>
            </div>
        @endif
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
            @if($showListActions)
                <x-button type="button" variant="outline" wire:click="$dispatch('openRetryModal')" class="h-9 text-sm">Retry jobs</x-button>
                <x-button type="button" variant="outline" wire:click="$dispatch('openCleanModal')" class="h-9 text-sm">Clean jobs</x-button>
            @endif
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full" data-resizable-table="{{ $showServiceColumn ? 'horizon-job-list' : 'horizon-service-dashboard' }}" data-column-ids="{{ $showServiceColumn ? 'service,queue,job,status,attempts,queued_at,processed,failed_at,runtime,actions' : 'queue,job,status,attempts,queued_at,processed,failed_at,runtime,actions' }}">
            <thead wire:ignore>
                <tr class="border-b border-border bg-muted/50">
                    @if($showServiceColumn)
                        <th class="table-header px-4 py-2.5" data-column-id="service">Service</th>
                    @endif
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
                        @if($showServiceColumn)
                            <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">{{ $job->service?->name ?? '–' }}</td>
                        @endif
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
                                @if(($job->status ?? '') === 'failed' && $job->service)
                                    <x-button
                                        variant="outline"
                                        type="button"
                                        wire:click="retryJob({{ $job->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="retryJob({{ $job->id }})"
                                        class="h-8 min-h-8 p-2 rounded-md"
                                        aria-label="Retry"
                                        title="Retry"
                                    >
                                        <span wire:loading.remove wire:target="retryJob({{ $job->id }})">
                                            <x-heroicon-o-arrow-path class="size-4" />
                                        </span>
                                        <span wire:loading wire:target="retryJob({{ $job->id }})" class="inline-flex" aria-hidden="true">
                                            <x-loader class="size-4" />
                                        </span>
                                    </x-button>
                                @endif
                                <x-button variant="secondary" class="h-8 min-h-8 p-2 rounded-md" aria-label="View" title="View" data-url="{{ route('horizon.jobs.show', ['job' => $job->id]) }}" onclick="var u = this.getAttribute('data-url'); (window.Livewire && window.Livewire.navigate ? window.Livewire.navigate(u) : (window.location.href = u))">
                                    <x-heroicon-o-eye class="size-4" />
                                </x-button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showServiceColumn ? 10 : 9 }}" data-column-id="{{ $showServiceColumn ? 'service' : 'queue' }}">
                            <div class="empty-state">
                                <x-heroicon-o-document-text class="empty-state-icon" />
                                @if($showServiceColumn)
                                    <p class="empty-state-title">No jobs yet</p>
                                    @if($services->isEmpty())
                                        <p class="empty-state-description">Register a service and push events from the Agent to see jobs here.</p>
                                        <x-button type="button" class="text-xs" onclick="window.location.href='{{ route('horizon.services.index') }}'">Register a service</x-button>
                                    @else
                                        <p class="empty-state-description">No jobs match the current filters.</p>
                                    @endif
                                @else
                                    <p class="empty-state-title">No jobs for this service</p>
                                    <p class="empty-state-description">Jobs will appear here when they are dispatched to this service.</p>
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
