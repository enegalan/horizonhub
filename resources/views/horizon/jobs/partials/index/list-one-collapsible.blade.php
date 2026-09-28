@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    /** @var string $resizableKey */
    /** @var string $bodyKey */
    /** @var bool $defer */
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
        'processing' => 'group-open:border-l-amber-500/60 group-open:bg-amber-500/5',
        'processed' => 'group-open:border-l-emerald-500/60 group-open:bg-emerald-500/5',
        'failed' => 'group-open:border-l-destructive/60 group-open:bg-destructive/5',
    ];
@endphp
<details
    data-section-key="{{ $sectionKey }}"
    :open="sectionOpen.{{ $sectionKey }}"
    class="group border-b border-border border-l-4 transition-colors duration-200 last:border-b-0 py-2 {{ $baseBorderClasses[$kind] }} {{ $openAccentClasses[$kind] }}"
>
    <summary
        class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 pr-5 text-section-title text-foreground sm:pr-6 [&::-webkit-details-marker]:hidden"
        @click="persistSectionFromSummary('{{ $sectionKey }}', $event)"
    >
        <x-icons.chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
        <span>{{ $titles[$kind] }}</span>
        <span id="job-count-{{ $bodyKey }}" class="{{ $badgeClasses[$kind] }}">{{ isset($paginator) && $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator ? $paginator->total() : 0 }}</span>
    </summary>
    <div class="pt-2">
        <x-table
            resizable-key="{{ $resizableKey }}"
            stream-patch-children
        >
            <x-slot:head>
                <x-table.th column="uuid">UUID</x-table.th>
                @if($showServiceColumn)
                    <x-table.th column="service" class="min-w-[100px]">Service</x-table.th>
                @endif
                <x-table.th column="queue" class="min-w-[100px]">Queue</x-table.th>
                <x-table.th column="job">Job</x-table.th>
                <x-table.th column="attempts" class="min-w-[100px]">Attempts</x-table.th>
                <x-table.th column="queued_at" class="min-w-[100px]">Queued at</x-table.th>
                @if($kind === 'processing')
                    <x-table.th column="delayed_until" class="min-w-[100px]">Delayed until</x-table.th>
                @elseif($kind === 'processed')
                    <x-table.th column="processed" class="min-w-[100px]">Processed</x-table.th>
                    <x-table.th column="runtime" class="min-w-[100px]">Runtime</x-table.th>
                @elseif($kind === 'failed')
                    <x-table.th column="failed_at" class="min-w-[100px]">Failed at</x-table.th>
                    <x-table.th column="runtime" class="min-w-[100px]">Runtime</x-table.th>
                @endif
                <x-table.th column="actions" class="min-w-[100px]" data-column-fixed>Actions</x-table.th>
            </x-slot:head>
            @include('horizon.jobs.partials.index.list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
                'defer' => $defer,
            ])
        </x-table>
        <div id="job-pagination-{{ $bodyKey }}" class="mt-2 px-4 py-2 sm:px-6">
            @include('horizon.jobs.partials.index.list-section-pagination', ['paginator' => $paginator])
        </div>
    </div>
</details>
