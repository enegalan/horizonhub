<div>
    <dt class="label-muted">Command</dt>
    <dd class="mt-0.5 font-mono text-foreground">{{ $job->name ?? $job->uuid }}</dd>
</div>
<div>
    <dt class="label-muted">UUID</dt>
    <dd class="mt-0.5 font-mono text-foreground">
        @php
            $uuid = $job->uuid ?? ($horizonJob['uuid'] ?? null ?? null);
        @endphp
        {{ $uuid ?? '–' }}
    </dd>
</div>
<div>
    <dt class="label-muted">Queue</dt>
    <dd class="mt-0.5 font-mono text-foreground">{{ $job->queue }}</dd>
</div>
<div>
    <dt class="label-muted">Connection</dt>
    <dd class="mt-0.5 font-mono text-foreground">
        {{ $horizonJob['connection'] ?? '–' }}
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
            $attemptsSource = isset($horizonJob['attempts']) && $horizonJob['attempts'] !== null
                ? $horizonJob['attempts']
                : $job->attempts;
            $attemptsDisplay = ($attemptsSource !== null && $attemptsSource > 0) ? $attemptsSource : '–';
        @endphp
        {{ $attemptsDisplay }}
    </dd>
</div>
<div>
    <dt class="label-muted">Retries</dt>
    <dd class="mt-0.5 text-foreground">
        @php
            $retries = $horizonJob['retries'] ?? null;
        @endphp
        {{ $retries !== null ? $retries : '–' }}
    </dd>
</div>
<div>
    <dt class="label-muted">Runtime</dt>
    <dd class="mt-0.5 text-foreground">{{ $job->runtime ?? '–' }}</dd>
</div>
<div>
    <dt class="label-muted">Queued at</dt>
    <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->queued_at ? \Carbon\Carbon::parse($job->queued_at)->toIso8601String() : '' }}">-</dd>
</div>
@if($job->available_at)
    <div>
        <dt class="label-muted">Delayed until</dt>
        <dd class="mt-0.5 text-foreground" data-datetime="{{ \Carbon\Carbon::parse($job->available_at)->toIso8601String() }}">-</dd>
    </div>
@endif
@if($job->status !== 'failed')
    <div>
        <dt class="label-muted">Processed at</dt>
        <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->processed_at ? \Carbon\Carbon::parse($job->processed_at)->toIso8601String() : '' }}">-</dd>
    </div>
@endif
@if($job->status === 'failed')
    <div>
        <dt class="label-muted">Failed at</dt>
        <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->failed_at ? \Carbon\Carbon::parse($job->failed_at)->toIso8601String() : '' }}">-</dd>
    </div>
@endif
@php
    $tags = $horizonJob['tags'] ?? [];
@endphp
@if(!empty($tags))
    <div class="sm:col-span-2">
        <dt class="label-muted">Tags</dt>
        <dd class="mt-0.5 text-foreground">
            {{ implode(', ', $tags) }}
        </dd>
    </div>
@endif
