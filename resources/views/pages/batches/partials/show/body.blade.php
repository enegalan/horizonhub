@php
    /** @var \App\Models\Service $service */
    /** @var string $batchId */
    /** @var array<string, mixed>|null $batch */
    /** @var list<array<string, mixed>> $failedJobs */

    $formatDate = static fn (mixed $value): string => $value instanceof \DateTimeInterface
        ? $value->format('Y-m-d H:i:s')
        : '—';
    $formatBool = static fn (?bool $value): string => $value === null ? '—' : ($value ? 'Yes' : 'No');

    $status = (string) ($batch['status'] ?? '');
@endphp

@if(! $service->enabled)
    @include('pages.partials.service-disabled-banner', [
        'message' => 'This service is disabled. Enable it to read batch details from its Horizon API.',
    ])
@elseif($batch === null)
    <x-empty-state
        title="Batch not found"
        :description="'Horizon did not report batch ' . $batchId . ' for this service. It may have expired or the service may be unavailable.'"
    >
        <div class="mt-4 flex flex-wrap justify-center gap-3">
            <a href="{{ route('horizon.batches.index', ['service_id' => [$service->id]]) }}" class="link text-sm" data-turbo-action="replace">All batches</a>
            <a href="{{ route('horizon.services.show', $service) }}" class="link text-sm" data-turbo-action="replace">Service dashboard</a>
        </div>
    </x-empty-state>
@else
    @php
        $batchName = (string) ($batch['name'] ?? '');
        $totalJobs = (int) ($batch['total_jobs'] ?? 0);
        $pendingJobs = (int) ($batch['pending_jobs'] ?? 0);
        $processedJobs = (int) ($batch['processed_jobs'] ?? 0);
        $failedJobsCount = (int) ($batch['failed_jobs'] ?? 0);
        $progress = (int) ($batch['progress'] ?? 0);
        $options = (array) ($batch['options'] ?? []);
        $queue = (string) ($options['queue'] ?? '');
        $connection = (string) ($options['connection'] ?? '');
        $allowFailures = \App\Support\BoolFormatter::orNull(
            $options['allowFailures'] ?? $options['allow_failures'] ?? null,
        );
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Batch</p>
            <h3 class="mt-1 text-lg font-semibold text-foreground break-all">
                {{ $batchName !== '' ? $batchName : (string) ($batch['id'] ?? $batchId) }}
            </h3>
            @if($batchName !== '')
                <p class="mt-1 font-mono text-xs text-muted-foreground break-all">{{ $batch['id'] ?? $batchId }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-batch-status :status="$status" />
            <a href="{{ route('horizon.batches.index', ['service_id' => [$service->id]]) }}" class="link text-sm" data-turbo-action="replace">All batches</a>
            <a href="{{ route('horizon.services.show', $service) }}" class="link text-sm" data-turbo-action="replace">Service dashboard</a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Progress" tone="violet">
            <div class="flex w-full items-center gap-2">
                <span>{{ $progress }}%</span>
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full bg-violet-500" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </x-stat-card>
        <x-stat-card label="Total jobs" tone="neutral" :value="number_format($totalJobs)" />
        <x-stat-card label="Processed" tone="emerald" :value="number_format($processedJobs)" />
        <x-stat-card label="Failed" tone="rose" :value="number_format($failedJobsCount)" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-border bg-muted/20 px-4 py-3">
                <h3 class="text-section-title text-foreground">Batch</h3>
            </div>
            <dl class="divide-y divide-border text-sm">
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">ID</dt>
                    <dd class="font-mono text-foreground break-all">{{ $batch['id'] ?? $batchId }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Name</dt>
                    <dd class="text-right text-foreground break-all">{{ $batchName !== '' ? $batchName : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Service</dt>
                    <dd class="text-right text-foreground break-all">
                        <a href="{{ route('horizon.services.show', $service) }}" class="link" data-turbo-action="replace">{{ $service->name }}</a>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Status</dt>
                    <dd><x-batch-status :status="$status" /></dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Created</dt>
                    <dd class="text-right text-foreground">{{ $formatDate($batch['created_at'] ?? null) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Finished</dt>
                    <dd class="text-right text-foreground">{{ $formatDate($batch['finished_at'] ?? null) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Cancelled</dt>
                    <dd class="text-right text-foreground">{{ $formatDate($batch['cancelled_at'] ?? null) }}</dd>
                </div>
            </dl>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-border bg-muted/20 px-4 py-3">
                <h3 class="text-section-title text-foreground">Execution</h3>
            </div>
            <dl class="divide-y divide-border text-sm">
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Queue</dt>
                    <dd class="text-right font-mono text-foreground break-all">{{ $queue !== '' ? $queue : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Connection</dt>
                    <dd class="text-right text-foreground break-all">{{ $connection !== '' ? $connection : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Total jobs</dt>
                    <dd class="text-right tabular-nums text-foreground">{{ number_format($totalJobs) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Pending jobs</dt>
                    <dd class="text-right tabular-nums text-foreground">{{ number_format($pendingJobs) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Processed jobs</dt>
                    <dd class="text-right tabular-nums text-foreground">{{ number_format($processedJobs) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Failed jobs</dt>
                    <dd class="text-right tabular-nums text-foreground">{{ number_format($failedJobsCount) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Allow failures</dt>
                    <dd class="text-right text-foreground">{{ $formatBool($allowFailures) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-6 card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/20 px-4 py-3">
            <h3 class="text-section-title text-foreground">Failed jobs</h3>
            <p class="text-xs text-muted-foreground">{{ number_format(\count($failedJobs)) }} failure(s)</p>
        </div>
        <x-table id="batch-failed-jobs">
            <x-slot:head>
                <x-table.th column="job" class="min-w-40">Job</x-table.th>
                <x-table.th column="queue" class="min-w-24">Queue</x-table.th>
                <x-table.th column="runtime" class="min-w-24">Runtime</x-table.th>
                <x-table.th column="failed_at" class="min-w-36">Failed at</x-table.th>
            </x-slot:head>
            @forelse($failedJobs as $failedJob)
                @php
                    $failedJobId = (string) ($failedJob['id'] ?? '');
                    $failedJobName = (string) ($failedJob['name'] ?? '');
                    $failedJobQueue = (string) ($failedJob['queue'] ?? '');
                @endphp
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm" data-column-id="job">
                        <a href="{{ route('horizon.jobs.show', ['job' => $failedJobId]) }}" class="link" data-turbo-action="replace" title="{{ $failedJobName !== '' ? $failedJobName : $failedJobId }}">
                            {{ $failedJobName !== '' ? class_basename($failedJobName) : $failedJobId }}
                        </a>
                    </td>
                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="queue">
                        {{ $failedJobQueue !== '' ? $failedJobQueue : '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="runtime">
                        {{ $failedJob['runtime'] ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="failed_at">
                        {{ $formatDate($failedJob['failed_at'] ?? null) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        This batch has no failed jobs.
                    </td>
                </tr>
            @endforelse
        </x-table>
    </div>

    @include('pages.partials.raw-options-details', [
        'options' => $options,
        'title' => 'Raw batch options',
    ])
@endif
