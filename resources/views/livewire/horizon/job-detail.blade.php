<div>
    <p class="mb-3 text-xs text-muted-foreground">
        <a href="{{ route('horizon.index') }}" wire:navigate class="link">Jobs</a>
        @if($job->service)
            / <a href="{{ route('horizon.services.show', $job->service) }}" wire:navigate class="link">{{ $job->service->name }}</a>
        @endif
        / <span class="text-foreground">{{ $job->name ?? $job->job_uuid }}</span>
    </p>
    <div class="card space-y-4 p-4">
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div><dt class="label-muted">Command</dt><dd class="mt-0.5 font-mono text-foreground">{{ $job->name ?? $job->job_uuid }}</dd></div>
            <div><dt class="label-muted">Queue</dt><dd class="mt-0.5 font-mono text-foreground">{{ $job->queue }}</dd></div>
            <div><dt class="label-muted">Status</dt><dd class="mt-0.5">@if($job->status === 'failed')<span class="badge-danger">{{ $job->status }}</span>@elseif($job->status === 'processed')<span class="badge-success">{{ $job->status }}</span>@else<span class="badge-muted">{{ $job->status }}</span>@endif</dd></div>
            <div><dt class="label-muted">Attempts</dt><dd class="mt-0.5 text-foreground">{{ $job->attempts ?? '–' }}</dd></div>
            <div><dt class="label-muted">Runtime</dt><dd class="mt-0.5 text-foreground">{{ $job->getFormattedRuntime() ?? '–' }}</dd></div>
            <div><dt class="label-muted">Queued at</dt><dd class="mt-0.5 text-foreground" data-datetime="{{ $job->queued_at?->toIso8601String() ?? '' }}">{{ $job->queued_at ? '…' : '–' }}</dd></div>
            <div><dt class="label-muted">Processed at</dt><dd class="mt-0.5 text-foreground" data-datetime="{{ $job->processed_at?->toIso8601String() ?? '' }}">{{ $job->processed_at ? '…' : '–' }}</dd></div>
            <div><dt class="label-muted">Failed at</dt><dd class="mt-0.5 text-foreground" data-datetime="{{ $job->failed_at?->toIso8601String() ?? '' }}">{{ $job->failed_at ? '…' : '–' }}</dd></div>
        </dl>
        @if($exception !== null && $exception !== '')
            <div>
                <dt class="label-muted mb-1">Error</dt>
                <pre class="mt-1 max-h-60 overflow-auto rounded-md border border-red-500/30 bg-red-500/5 p-3 text-xs text-foreground whitespace-pre-wrap break-words">{!! e(html_entity_decode($exception ?? '', ENT_QUOTES | ENT_HTML401, 'UTF-8')) !!}</pre>
            </div>
        @endif
        @if($job->payload)
            <div>
                <dt class="label-muted mb-1">Payload</dt>
                <pre class="mt-1 max-h-52 overflow-auto rounded-md border border-border bg-muted/30 p-3 text-xs text-foreground">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
        @if($job->service && $job->service->base_url)
            <div class="flex gap-2 pt-1">
                @if($job->status === 'failed')
                    <x-button type="button" wire:click="retry" wire:loading.attr="disabled" class="h-8 min-h-8 p-2 relative" aria-label="Retry" title="Retry">
                        <span wire:loading.remove wire:target="retry">
                            <x-heroicon-o-arrow-path class="size-4" />
                        </span>
                        <span wire:loading wire:target="retry" class="inline-flex" aria-hidden="true">
                            <x-loader />
                        </span>
                    </x-button>
                @endif
                @if($job->status !== 'processing')
                    <x-button variant="destructive" type="button" wire:click="confirmDelete" class="h-8 min-h-8 p-2" aria-label="Delete" title="Delete">
                        <x-heroicon-o-trash class="size-4" />
                    </x-button>
                @endif
            </div>
        @endif
    </div>
    @if($showDeleteModal)
        <x-confirm-modal
            title="Delete job"
            :message="'Are you sure you want to permanently delete this job? This cannot be undone.'"
            variant="danger"
            size="sm"
            confirmText="Delete"
            cancelText="Cancel"
            confirmAction="delete"
            cancelAction="cancelDelete"
            backdropAction="cancelDelete"
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
