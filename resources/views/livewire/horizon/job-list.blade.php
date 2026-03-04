<div>
    <div class="card">
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Service</x-input-label>
                <x-select wire:model.live="serviceFilter" class="w-44">
                    <option value="">All</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->status }})</option>
                    @endforeach
                </x-select>
            </div>
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Queue</x-input-label>
                <x-text-input type="text" wire:model.live.debounce.300ms="queueFilter" placeholder="Filter" class="w-36" />
            </div>
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Status</x-input-label>
                <x-select wire:model.live="statusFilter" class="w-32" :options="array('' => 'All', 'processed' => 'Processed', 'failed' => 'Failed', 'processing' => 'Processing')" />
            </div>
            <div class="space-y-1.5">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Job type</x-input-label>
                <x-text-input type="text" wire:model.live.debounce.300ms="jobTypeFilter" placeholder="Class" class="w-44" />
            </div>
            <x-button type="button" variant="outline" wire:click="openCleanModal" class="h-9 text-sm ml-auto">Clean jobs</x-button>
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
                                    <a href="{{ route('horizon.jobs.show', ['job' => $job->id]) }}" wire:navigate class="btn-secondary inline-flex items-center justify-center h-8 min-h-8 p-2 rounded-md" aria-label="View" title="View">
                                        <x-heroicon-o-eye class="size-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" data-column-id="service">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <p class="empty-state-title">No jobs yet</p>
                                    @if(in_array($statusFilter, ['processed', 'processing'], true))
                                        <p class="empty-state-description">Processed and processing jobs appear when workers complete (or start) jobs successfully. If you only see failed jobs, the agent is likely only sending JobFailed events (e.g. demo app runs GenerateReport which fails on purpose). To see processed jobs: run jobs that complete successfully and ensure the agent pushes JobProcessed (e.g. in the demo container: <code class="text-xs bg-muted px-1 rounded">php artisan demo:dispatch-successful-jobs</code>).</p>
                                    @else
                                        <p class="empty-state-description">Register a service and push events from the Agent to see jobs here.</p>
                                        <a href="{{ route('horizon.services.index') }}" wire:navigate class="btn-primary text-xs">Register a service</a>
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
                    <div class="space-y-3">
                        <div class="space-y-1.5">
                            <x-input-label class="text-[11px] font-medium text-muted-foreground">Service</x-input-label>
                            <x-select wire:model.live="cleanServiceId" class="w-full">
                                <option value="">All</option>
                                @foreach($services as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="space-y-1.5">
                            <x-input-label class="text-[11px] font-medium text-muted-foreground">Status</x-input-label>
                            <x-select wire:model.live="cleanStatus" class="w-full" :options="array('' => 'All', 'processed' => 'Processed', 'failed' => 'Failed', 'processing' => 'Processing')" />
                        </div>
                        <div class="space-y-1.5">
                            <x-input-label class="text-[11px] font-medium text-muted-foreground">Job type</x-input-label>
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
    window.addEventListener('horizon-hub-refresh', function () {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
