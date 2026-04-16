@php
    /** @var string $kind processing|processed|failed */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    $emptyCopy = [
        'processing' => ['title' => 'No processing jobs', 'description' => 'Jobs currently being executed will appear here.'],
        'processed' => ['title' => 'No processed jobs', 'description' => 'Completed jobs will appear here.'],
        'failed' => ['title' => 'No failed jobs', 'description' => 'Failed jobs will appear here.'],
    ];
@endphp
@forelse($paginator as $job)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="{{ $job->uuid }}">
        <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
            <a
                class="link"
                href="{{ route('horizon.jobs.show', ['job' => $job->uuid, 'service_id' => $job->service->id]) }}"
                data-turbo-frame="_top"
                data-turbo-action="replace"
            >
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
        <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
        @if($kind === 'processing')
            <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="delayed_until">{{ $job->available_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
        @elseif($kind === 'processed')
            <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="processed">{{ $job->processed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
            <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="runtime">{{ $job->runtime ?? '–' }}</td>
        @elseif($kind === 'failed')
            <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="failed_at">{{ $job->failed_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
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
    <tr data-stream-row-id="__empty-{{ $kind }}">
        <td colspan="9" data-column-id="{{ $showServiceColumn ? 'service' : 'queue' }}">
            <div class="empty-state">
                <x-heroicon-o-document-text class="empty-state-icon" />
                <p class="empty-state-title">{{ $emptyCopy[$kind]['title'] }}</p>
                <p class="empty-state-description">{{ $emptyCopy[$kind]['description'] }}</p>
            </div>
        </td>
    </tr>
@endforelse
