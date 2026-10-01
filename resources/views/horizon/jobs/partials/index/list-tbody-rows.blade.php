@php
    /** @var \App\Enums\JobSection|string $section */
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    $jobSection = $section instanceof \App\Enums\JobSection
        ? $section
        : \App\Enums\JobSection::normalize($section);
    $emptyCopy = $jobSection->emptyCopy();
    $skeletonColumns = $jobSection->skeletonColumns() + ($showServiceColumn ? 1 : 0);
    $isFailed = $jobSection === \App\Enums\JobSection::Failed;
    $paginatorTotal = isset($paginator) && $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator ? $paginator->total() : 0;

    /**
     * Cell value for a section-specific column.
     */
    $cellValue = static function (string $column, $job): string {
        $timestamp = match ($column) {
            'delayed_until' => $job->available_at,
            'processed' => $job->processed_at,
            'failed_at' => $job->failed_at,
            default => null,
        };

        return match ($column) {
            'runtime' => (string) ($job->runtime ?? '–'),
            default => $timestamp?->format('Y-m-d H:i:s') ?? '–',
        };
    };
@endphp
@if(! empty($defer) && $paginatorTotal === 0)
    <x-skeleton.table-rows rows="6" :columns="$skeletonColumns" />
@else
@forelse($paginator ?? [] as $job)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="{{ $job->uuid }}">
        <td class="px-4 py-2.5 text-sm text-primary cursor-pointer truncate max-w-[180px]" data-column-id="uuid">
            <a
                class="link"
                href="{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}"
                data-turbo-frame="_top"
                data-turbo-action="replace"
            >
                {{ $job->uuid }}
            </a>
        </td>
        @if($showServiceColumn)
            <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate max-w-[180px]" data-column-id="service">
                @if($job->service)
                    <a href="{{ route('horizon.services.show', $job->service) }}" class="link" data-turbo-action="replace" title="{{ $job->service->name }}">{{ $job->service->name }}</a>
                @else
                    –
                @endif
            </td>
        @endif
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queue">{{ $job->queue }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="job">{{ $job->name ?? $job->uuid }}</td>
        <td @class([
            'px-4 py-2.5 text-sm text-muted-foreground',
            'min-w-[80px]' => $isFailed && ! $showServiceColumn,
        ]) data-column-id="attempts">
            @php $attempts = $job->attempts; $attemptsDisplay = ($attempts !== null && $attempts > 0) ? $attempts : '–'; @endphp
            {{ $attemptsDisplay }}
        </td>
        <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="queued_at">{{ $job->queued_at?->format('Y-m-d H:i:s') ?? '–' }}</td>
        @foreach($jobSection->columns() as $column)
            <td class="px-4 py-2.5 text-sm text-muted-foreground truncate max-w-[180px]" data-column-id="{{ $column['column'] }}">{{ $cellValue($column['column'], $job) }}</td>
        @endforeach
        <td class="px-4 py-2.5" data-column-id="actions">
            @include('horizon.jobs.partials.index.row-actions', [
                'job' => $job,
                'pageService' => $pageService,
                'showRetry' => $isFailed && $job->service,
            ])
        </td>
    </tr>
@empty
    <tr data-stream-row-id="__empty-{{ $jobSection->value }}">
        <td colspan="9" data-column-id="{{ $showServiceColumn ? 'service' : 'queue' }}">
            <x-empty-state
                title="{{ $emptyCopy['title'] }}"
                description="{{ $emptyCopy['description'] }}"
            >
                <x-slot name="icon">
                    <x-icons.document-text class="empty-state-icon" />
                </x-slot>
            </x-empty-state>
        </td>
    </tr>
@endforelse
@endif
