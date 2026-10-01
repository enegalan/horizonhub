@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    /** @var string $id */
    /** @var string $bodyKey */
    /** @var bool $defer */
    /** @var \App\Enums\JobSection|string $section */
    $jobSection = $section instanceof \App\Enums\JobSection
        ? $section
        : \App\Enums\JobSection::normalize($section);
    $kind = $jobSection->value;
    $sectionKey = $jobSection->value;
@endphp
<details
    data-section-key="{{ $sectionKey }}"
    :open="sectionOpen.{{ $sectionKey }}"
    class="group border-b border-border border-l-4 transition-colors duration-200 last:border-b-0 py-2 {{ $jobSection->borderClass() }} {{ $jobSection->openAccentClass() }}"
>
    <summary
        class="flex cursor-pointer list-none items-center gap-2 py-2 pl-4 pr-5 text-section-title text-foreground sm:pr-6 [&::-webkit-details-marker]:hidden"
        @click="persistSectionFromSummary('{{ $sectionKey }}', $event)"
    >
        <x-icons.chevron-down class="size-4 shrink-0 transition-transform group-open:rotate-180" aria-hidden="true" />
        <span>{{ $jobSection->label() }}</span>
        <span id="job-count-{{ $bodyKey }}" class="{{ $jobSection->badgeClass() }}">{{ isset($paginator) && $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator ? $paginator->total() : 0 }}</span>
    </summary>
    <div class="pt-2">
        <x-table
            id="{{ $id }}"
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
                @foreach($jobSection->columns() as $column)
                    <x-table.th :column="$column['column']" :class="$column['class'] ?? null">{{ $column['label'] }}</x-table.th>
                @endforeach
                <x-table.th column="actions" class="min-w-[100px]" data-column-fixed>Actions</x-table.th>
            </x-slot:head>
            @include('horizon.jobs.partials.index.list-tbody-rows', [
                'section' => $jobSection,
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
