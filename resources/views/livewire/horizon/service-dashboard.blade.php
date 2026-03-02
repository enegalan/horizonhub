<div>
    <p class="mb-3 text-xs text-muted-foreground">
        <a href="{{ route('horizon.index') }}" wire:navigate class="link">Jobs</a> /
        <a href="{{ route('horizon.services.index') }}" wire:navigate class="link">Services</a> /
        <span class="text-foreground">{{ $service->name }}</span>
    </p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Jobs past minute</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastMinute) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Jobs past hour</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastHour) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Failed (past 7 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($failedPastSevenDays) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Processed (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($processedPast24Hours) }}</p>
        </div>
    </div>

    <div class="card mb-4 p-4">
        <h3 class="text-section-title text-foreground mb-2">Supervisors</h3>
        @if($supervisors->isNotEmpty())
            <div class="space-y-2">
                @foreach($supervisors as $supervisor)
                    @php
                        $lastSeen = $supervisor->last_seen_at;
                        $minutesAgo = $lastSeen ? (int) now()->diffInMinutes($lastSeen) : null;
                        if ($minutesAgo === null || $minutesAgo > 5) {
                            $statusColor = 'bg-red-500';
                            $statusTitle = 'Offline';
                        } elseif ($minutesAgo > 2) {
                            $statusColor = 'bg-amber-500';
                            $statusTitle = 'Stale';
                        } else {
                            $statusColor = 'bg-emerald-500';
                            $statusTitle = 'Online';
                        }
                    @endphp
                    <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $statusColor }}" title="{{ $statusTitle }}" aria-label="{{ $statusTitle }}"></span>
                            <span class="font-mono text-sm text-foreground">{{ $supervisor->name }}</span>
                        </div>
                        <span class="text-xs text-muted-foreground" title="Last seen">Last seen {{ $supervisor->last_seen_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-muted-foreground">Supervisor data is not available. Run <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">php artisan horizon</code> on the service (not <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">queue:work</code>), ensure the Horizon Hub Agent is installed and configured, and wait a few seconds for supervisor heartbeats.</p>
        @endif
    </div>

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-service-dashboard" data-column-ids="queue,job,status,attempts,queued_at,processed,failed_at,runtime,actions">
                <thead wire:ignore>
                    <tr class="border-b border-border bg-muted/50">
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
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="queued_at">{{ ($job->queued_at ?? $job->created_at)?->format('Y-m-d H:i:s') ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="processed">{{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground" data-column-id="failed_at">{{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
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
                            <td colspan="9" data-column-id="queue">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    <p class="empty-state-title">No jobs for this service</p>
                                    <p class="empty-state-description">Jobs will appear here when they are dispatched to this service.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-border px-4 py-2">
            <x-ui.pagination :paginator="$jobs" />
        </div>
    </div>
</div>

@script
<script>
    window.addEventListener('horizon-hub-refresh', function () {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
