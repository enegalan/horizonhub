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
        'processing' => 'group-open:border-l-amber-500/60 group-open:bg-amber-500/5',
        'processed' => 'group-open:border-l-emerald-500/60 group-open:bg-emerald-500/5',
        'failed' => 'group-open:border-l-destructive/60 group-open:bg-destructive/5',
    ];
@endphp
<details
    data-section-key="{{ $sectionKey }}"
    :open="sectionOpen.{{ $sectionKey }}"
    class="group border-border border-l-4 transition-colors duration-200 py-2 {{ $baseBorderClasses[$kind] }} {{ $openAccentClasses[$kind] }}"
>
    <summary
        class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 text-section-title text-foreground [&::-webkit-details-marker]:hidden"
        @click="persistSectionFromSummary('{{ $sectionKey }}', $event)"
    >
        <x-heroicon-o-chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
        <span>{{ $titles[$kind] }}</span>
        <span id="job-count-{{ $bodyKey }}" class="{{ $badgeClasses[$kind] }}">{{ $paginator->total() }}</span>
    </summary>
    <div class="pt-2">
        <x-table
            resizable-key="{{ $resizableKey }}"
            column-ids="{{ $columnIds }}"
            body-key="{{ $bodyKey }}"
            body-id="turbo-tbody-{{ $bodyKey }}"
            stream-patch-children
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
                    @if($kind === 'processing')
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="delayed_until">Delayed until</th>
                    @elseif($kind === 'processed')
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="processed">Processed</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="runtime">Runtime</th>
                    @elseif($kind === 'failed')
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="failed_at">Failed at</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="runtime">Runtime</th>
                    @endif
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="actions">Actions</th>
                </tr>
            </x-slot:head>
            @include('horizon.jobs.partials.job-list-tbody-rows', [
                'kind' => $kind,
                'paginator' => $paginator,
                'showServiceColumn' => $showServiceColumn,
                'pageService' => $pageService,
            ])
        </x-table>
        <div id="job-pagination-{{ $bodyKey }}" class="px-4 py-2 mt-2">
            @include('horizon.jobs.partials.job-list-section-pagination', ['paginator' => $paginator])
        </div>
    </div>
</details>
