@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonJobDetail({
            retryUrl: '{{ route('horizon.jobs.retry', ['uuid' => $job->uuid]) }}',
            canRetry: {{ $job->service && $job->service->base_url && $job->status === 'failed' ? 'true' : 'false' }},
        })"
        x-init="typeof init === 'function' ? init() : null"
        data-horizon-job-detail-root="1"
    >
        <p class="mb-3 text-xs text-muted-foreground">
            <a href="{{ route('horizon.index') }}" class="link">Jobs</a>
            @if($job->service)
                / <a href="{{ route('horizon.services.show', $job->service) }}" class="link">{{ $job->service->name }}</a>
            @endif
            / <span class="text-foreground">{{ $job->name ?? $job->uuid }}</span>
        </p>
        <div class="card space-y-4 p-4">
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
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
                <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->queued_at ? \Carbon\Carbon::parse($job->queued_at)->toIso8601String() : '' }}">{{ $job->queued_at ? '…' : '–' }}</dd>
            </div>
            @if($job->status !== 'failed')
                <div>
                    <dt class="label-muted">Processed at</dt>
                    <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->processed_at ? \Carbon\Carbon::parse($job->processed_at)->toIso8601String() : '' }}">{{ $job->processed_at ? '…' : '–' }}</dd>
                </div>
            @endif
            <div>
                <dt class="label-muted">Failed at</dt>
                <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->failed_at ? \Carbon\Carbon::parse($job->failed_at)->toIso8601String() : '' }}">{{ $job->failed_at ? '…' : '–' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="label-muted">Tags</dt>
                <dd class="mt-0.5 text-foreground">
                    @php
                        $tags = $horizonJob['tags'] ?? [];
                    @endphp
                    @if(!empty($tags))
                        {{ implode(', ', $tags) }}
                    @else
                        –
                    @endif
                </dd>
            </div>
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
        <div class="flex flex-wrap gap-2 pt-1">
            @if($job->service && $job->service->base_url && $job->status === 'failed')
                <x-button
                    type="button"
                    class="h-8 min-h-8 p-2 relative"
                    aria-label="Retry"
                    title="Retry"
                    x-bind:disabled="retrying"
                    @click="retry()"
                >
                    <span x-show="!retrying">
                        <x-heroicon-o-arrow-path class="size-4" />
                    </span>
                    <span x-show="retrying" class="inline-flex" aria-hidden="true">
                        <x-loader />
                    </span>
                </x-button>
            @endif
            @php
                $serviceForDashboard = $job->service ?? null;
                $dashboardBase = $serviceForDashboard
                    ? ($serviceForDashboard->public_url ?: $serviceForDashboard->base_url)
                    : null;
                $jobUuidForDashboard = $job->uuid ?? ($horizonJob['uuid'] ?? null);
                $horizonDashboardPath = \rtrim(\config('horizonhub.horizon_paths.dashboard'), '/');
                $horizonJobUrl = null;
                if ($dashboardBase && $jobUuidForDashboard) {
                    $horizonJobUrl = \rtrim($dashboardBase, '/') . $horizonDashboardPath . '/jobs/' . \urlencode((string) $jobUuidForDashboard);
                }
            @endphp
            @if($horizonJobUrl)
                <x-button
                    type="button"
                    variant="secondary"
                    class="h-8 min-h-8 px-3 inline-flex items-center gap-1"
                    aria-label="Open in Horizon dashboard"
                    title="Open in Horizon dashboard"
                    onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
                >
                    <x-heroicon-o-window class="size-4" />
                    <span class="text-xs font-medium">Open in Horizon</span>
                </x-button>
            @endif
        </div>
    </div>
@endsection
