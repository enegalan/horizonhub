<div>
    <dt class="label-muted">Command</dt>
    <dd class="mt-0.5 font-mono text-foreground">{{ $job->name ?? '–' }}</dd>
</div>
<div>
    <dt class="label-muted">UUID</dt>
    <dd class="mt-0.5 font-mono text-foreground">
        {{ $job->uuid ?? '–' }}
    </dd>
</div>
<div>
    <dt class="label-muted">Queue</dt>
    <dd class="mt-0.5 font-mono text-foreground">{{ $job->queue ?? '–' }}</dd>
</div>
<div>
    <dt class="label-muted">Connection</dt>
    <dd class="mt-0.5 font-mono text-foreground">
        {{ $job->connection ?? '–' }}
    </dd>
</div>
<div>
    <dt class="label-muted">Status</dt>
    <dd class="mt-0.5">
        @if($job->status === 'failed')
            <span class="badge-danger">{{ $job->status }}</span>
        @elseif($job->status === 'processed')
            <span class="badge-success">{{ $job->status }}</span>
        @else
            <span class="badge-muted">{{ $job->status }}</span>
        @endif
    </dd>
</div>
<div>
    <dt class="label-muted">Attempts</dt>
    <dd class="mt-0.5 text-foreground">
        @php
            $attemptsDisplay = $job->attempts !== null ? $job->attempts : 0;
        @endphp
        {{ $attemptsDisplay }}
    </dd>
</div>
<div>
    <dt class="label-muted">Retries</dt>
    <dd class="mt-0.5 text-foreground">
        {{ $job->retries !== null ? $job->retries : 0 }}
    </dd>
</div>
<div>
    <dt class="label-muted">Runtime</dt>
    <dd class="mt-0.5 text-foreground">{{ $job->runtime ?? '–' }}</dd>
</div>
<div>
    <dt class="label-muted">Queued at</dt>
    <dd class="mt-0.5 text-foreground">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</dd>
</div>
@if($job->available_at)
    <div>
        <dt class="label-muted">Delayed until</dt>
        <dd class="mt-0.5 text-foreground">{{ $job->available_at?->format('Y-m-d H:i:s') ?? '–' }}</dd>
    </div>
@endif
@if($job->status !== 'failed')
    <div>
        <dt class="label-muted">Processed at</dt>
        <dd class="mt-0.5 text-foreground">{{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}</dd>
    </div>
@endif
@if($job->status === 'failed')
    <div>
        <dt class="label-muted">Failed at</dt>
        <dd class="mt-0.5 text-foreground">{{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}</dd>
    </div>
@endif
@if(!empty($job->tags))
    <div class="sm:col-span-2">
        <dt class="label-muted">Tags</dt>
        <dd class="mt-0.5 text-foreground">
            {{ implode(', ', $job->tags) }}
        </dd>
    </div>
@endif
