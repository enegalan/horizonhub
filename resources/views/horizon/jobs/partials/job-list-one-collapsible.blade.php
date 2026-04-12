@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    /** @var string $resizableKey */
    /** @var string $bodyKey */
    /** @var string $columnIds */
    /** @var string $kind processing|processed|failed */
    $sectionKey = $kind;
    $titles = [
        'processing' => 'Processing',
        'processed' => 'Processed',
        'failed' => 'Failed',
    ];
    $badgeClasses = [
        'processing' => 'badge-warning',
        'processed' => 'badge-success',
        'failed' => 'badge-danger',
    ];
    $baseBorderClasses = [
        'processing' => 'border-l-amber-500/40 hover:border-l-amber-500/60',
        'processed' => 'border-l-emerald-500/40 hover:border-l-emerald-500/60',
        'failed' => 'border-l-destructive/40 hover:border-l-destructive/60',
    ];
    $openAccentClasses = [
        'processing' => 'border-l-amber-500/60 bg-amber-500/5',
        'processed' => 'border-l-emerald-500/60 bg-emerald-500/5',
        'failed' => 'border-l-destructive/60 bg-destructive/5',
    ];
    $emptyCopy = [
        'processing' => ['title' => 'No processing jobs', 'description' => 'Jobs currently being executed will appear here.'],
        'processed' => ['title' => 'No processed jobs', 'description' => 'Completed jobs will appear here.'],
        'failed' => ['title' => 'No failed jobs', 'description' => 'Failed jobs will appear here.'],
    ];
@endphp
<details
    class="group border-border border-l-4 transition-colors duration-200 py-2 {{ $baseBorderClasses[$kind] }}"
    :open="sectionOpen.{{ $sectionKey }}"
    :class="{ '{{ $openAccentClasses[$kind] }}': sectionOpen.{{ $sectionKey }} }"
    @toggle="onToggle('{{ $sectionKey }}', $event)"
>
    <summary class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 text-section-title text-foreground [&::-webkit-details-marker]:hidden">
        <x-heroicon-o-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
        <span>{{ $titles[$kind] }}</span>
        <span class="{{ $badgeClasses[$kind] }}">{{ $paginator->total() }}</span>
    </summary>
    <div class="pt-2">
        <x-table
            resizable-key="{{ $resizableKey }}"
            column-ids="{{ $columnIds }}"
            body-key="{{ $bodyKey }}"
        >
            <x-slot:head>
                <tr class="border-b border-border bg-muted/50">
                    <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                    @if($showServiceColumn)
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="service">Service</th>
                    @endif
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                    <th class="table-header px-4 py-2.5" data-column-id="job">Job</th>
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="attempts">Attempts</th>
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queued_at">Queued at</th>
                    @if($kind === 'processed')
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="processed">Processed</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="runtime">Runtime</th>
                    @elseif($kind === 'failed')
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="failed_at">Failed at</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="runtime">Runtime</th>
                    @endif
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="actions">Actions</th>
                </tr>
            </x-slot:head>
            @forelse($paginator as $job)
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
                        <a class="link" href="{{ route('horizon.jobs.show', ['job' => $job->uuid, 'service_id' => $job->service->id]) }}" data-turbo-action="replace">
                            {{ $job->uuid }}
                        </a>
                    </td>
                    @if($showServiceColumn)
                        <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate max-w-[180px]" data-column-id="service">
                            @if($job->service)
                                <a href="{{ route('horizon.services.show', $job->service) }}" class="link" data-turbo-action="replace">{{ $job->service->name }}</a>
                            @else
                                –
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue">{{ $job->queue }}</td>
                    <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->uuid }}</td>
                    <td @class([
                        'px-4 py-2.5 text-sm text-muted-foreground',
                        'min-w-[80px]' => $kind === 'failed' && ! $showServiceColumn,
                    ]) data-column-id="attempts">
                        @php $attempts = $job->attempts; $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–'; @endphp
                        {{ $attemptsDisplay }}
                    </td>
                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at" data-datetime="{{ $job->queued_at?->format('c') ?? '' }}">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                    @if($kind === 'processed')
                        <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="processed" data-datetime="{{ $job->processed_at?->format('c') ?? '' }}">{{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="runtime">{{ $job->runtime ?? '–' }}</td>
                    @elseif($kind === 'failed')
                        <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="failed_at" data-datetime="{{ $job->failed_at?->format('c') ?? '' }}">{{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="runtime">{{ $job->runtime ?? '–' }}</td>
                    @endif
                    <td class="px-4 py-2.5" data-column-id="actions">
                        @include('horizon.jobs.partials.job-row-actions', [
                            'job' => $job,
                            'pageService' => $pageService,
                            'showRetry' => $kind === 'failed' && $job->service && $job->service->base_url,
                        ])
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" data-column-id="{{ $showServiceColumn ? 'service' : 'queue' }}">
                        <div class="empty-state">
                            <x-heroicon-o-document-text class="empty-state-icon" />
                            <p class="empty-state-title">{{ $emptyCopy[$kind]['title'] }}</p>
                            <p class="empty-state-description">{{ $emptyCopy[$kind]['description'] }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </x-table>
        @if($paginator->total() > 0)
            <div class="px-4 py-2 mt-2">
                <x-pagination :paginator="$paginator" />
            </div>
        @endif
    </div>
</details>
