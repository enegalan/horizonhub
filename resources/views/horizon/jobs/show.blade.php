@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonJobDetail({
            retryUrl: '{{ route('horizon.jobs.retry', ['uuid' => $job->uuid, 'service_id' => $job->service->id]) }}',
            canRetry: {{ $job->service && $job->service->base_url && $job->status === 'failed' ? 'true' : 'false' }},
        })"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-job-detail"
        data-horizon-job-uuid="{{ $job->uuid ? e($job->uuid) : '' }}"
    >
        <p class="mb-3 text-xs text-muted-foreground">
            <a href="{{ route('horizon.index') }}" class="link" data-turbo-action="replace">Jobs</a>
            @if($job->service)
                / <a href="{{ route('horizon.services.show', $job->service) }}" class="link" data-turbo-action="replace">{{ $job->service->name }}</a>
            @endif
            / <span class="text-foreground">{{ $job->name ?? $job->uuid }}</span>
        </p>
        <div class="card space-y-4 p-4">
        <div class="flex flex-wrap gap-2">
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
                $jobUuidForDashboard = $job->uuid ?? ($horizonJob['uuid'] ?? null);
                $jobStatusForDashboard = (string) ($job->status ?? '');
                $horizonJobUrl = \App\Support\Horizon\JobDashboardUrlBuilder::build(
                    $serviceForDashboard,
                    $jobUuidForDashboard,
                    $jobStatusForDashboard
                );
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
                <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->queued_at ? \Carbon\Carbon::parse($job->queued_at)->toIso8601String() : '' }}">-</dd>
            </div>
            @if($job->status !== 'failed')
                <div>
                    <dt class="label-muted">Processed at</dt>
                    <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->processed_at ? \Carbon\Carbon::parse($job->processed_at)->toIso8601String() : '' }}">-</dd>
                </div>
            @endif
            <div>
                <dt class="label-muted">Failed at</dt>
                <dd class="mt-0.5 text-foreground" data-datetime="{{ $job->failed_at ? \Carbon\Carbon::parse($job->failed_at)->toIso8601String() : '' }}">-</dd>
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
        @if(count($exception) > 0)
            <div>
                <dt class="label-muted mb-1">Error</dt>
                <div class="flex flex-col items-start mt-1 rounded-md border border-red-500/30 bg-red-500/5 text-sm text-foreground break-words">
                    @foreach($exception as $lineIndex => $line)
                        <code
                            @class([
                                'w-full py-1 px-3 leading-10 border-b whitespace-pre-wrap break-words border-red-500/20'
                            ])
                            x-show="showAllExceptionLines || {{ $lineIndex < \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1) ? 'true' : 'false' }}"
                            @if($lineIndex >= \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1)) x-cloak @endif
                        >{{ $line }}</code>
                    @endforeach
                    @if(\count($exception) > \App\Support\ConfigHelper::getIntWithMin('horizonhub.failed_job_exception_preview_lines', 1))
                        <button
                            type="button"
                            class="mx-4 my-4 font-medium text-primary-solid"
                            @click="toggleExceptionLines()"
                            x-text="showAllExceptionLines ? 'Show less' : 'Show all'"
                            no-ring
                        ></button>
                    @endif
                </div>
            </div>
        @endif
        @if($job->status === 'failed')
            <div>
                <dt class="label-muted mb-1">Exception context</dt>
                <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-foreground break-words">
                    <div
                        data-json-tree="context"
                        data-json-source="{{ e($context) }}"
                    ></div>
                </div>
            </div>
        @endif
        @if($job->status === 'failed' && count($retryHistory) > 0)
            <div>
                <dt class="label-muted mb-1">Retries history</dt>
                <x-table
                    resizable-key="horizon-job-retry-history"
                    column-ids="uuid,status,retried_at"
                    body-key="horizon-job-retry-history"
                >
                    <x-slot:head>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="status">Status</th>
                            <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="retried_at">Retried at</th>
                        </tr>
                    </x-slot:head>
                    @foreach($retryHistory as $retryJob)
                        @php
                            $retriedAtIso = null;
                            if (isset($retryJob['retried_at']) && \is_numeric($retryJob['retried_at'])) {
                                $retriedAtIso = \Carbon\Carbon::createFromTimestamp((int) $retryJob['retried_at'])->toIso8601String();
                            }
                            $retryStatus = isset($retryJob['status']) && \is_string($retryJob['status']) && $retryJob['status'] !== ''
                                ? $retryJob['status']
                                : null;
                        @endphp
                        <tr class="transition-colors hover:bg-muted/30">
                            <td class="px-4 py-2.5 text-sm text-primary truncate max-w-[180px]" data-column-id="uuid">
                                @if(isset($retryJob['id']) && \is_string($retryJob['id']) && $retryJob['id'] !== '' && $job->service)
                                    <a class="link" href="{{ route('horizon.jobs.show', ['job' => $retryJob['id'], 'service_id' => $job->service->id]) }}" data-turbo-action="replace">{{ $retryJob['id'] }}</a>
                                @else
                                    {{ $retryJob['id'] ?? '–' }}
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="status">
                                @if($retryStatus === 'failed')
                                    <span class="badge-danger">{{ $retryStatus }}</span>
                                @elseif($retryStatus === 'processed' || $retryStatus === 'completed')
                                    <span class="badge-success">{{ $retryStatus }}</span>
                                @elseif($retryStatus === 'processing')
                                    <span class="badge-warning">{{ $retryStatus }}</span>
                                @elseif($retryStatus !== null)
                                    <span class="badge-muted">{{ $retryStatus }}</span>
                                @else
                                    –
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="retried_at" data-datetime="{{ $retriedAtIso ?? '' }}">-</td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        @endif
        <div>
            <dt class="label-muted mb-1">Data</dt>
            <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-xs font-mono text-foreground break-words">
                <div
                    data-json-tree="data"
                    data-json-source="{{ e($commandData ?? 'null') }}"
                ></div>
            </div>
        </div>
        <div>
            <dt class="label-muted mb-1">Payload</dt>
            <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-xs font-mono text-foreground break-words">
                <div
                    data-json-tree="payload"
                    data-json-source="{{ e($payload ?? 'null') }}"
                ></div>
            </div>
        </div>
    </div>
@endsection
