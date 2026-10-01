@php
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    /** @var string $resizablePrefix */
    $sections = [
        \App\Enums\JobSection::Processing->value => $jobsProcessing,
        \App\Enums\JobSection::Processed->value => $jobsProcessed,
        \App\Enums\JobSection::Failed->value => $jobsFailed,
    ];
    $sectionOpenFallback = \Illuminate\Support\Js::from(
        \array_fill_keys(\array_keys($sections), true)
    );
@endphp
<div
    class="rounded-bl-[var(--radius)] overflow-hidden"
    id="horizon-jobs-stack"
    x-data="{
        sectionOpen: (() => {
            const fallback = {{ $sectionOpenFallback }};
            try {
                const raw = localStorage.getItem('horizon_jobs_sections');
                if (!raw) return fallback;
                return Object.assign({}, fallback, JSON.parse(raw));
            } catch (e) {
                return fallback;
            }
        })(),
        persistSectionFromSummary(section, event) {
            const details = event && event.currentTarget
                ? event.currentTarget.closest('details[data-section-key]')
                : null;
            if (!details) return;
            requestAnimationFrame(() => {
                this.sectionOpen[section] = details.open;
                localStorage.setItem('horizon_jobs_sections', JSON.stringify(this.sectionOpen));
            });
        }
    }"
>
    @foreach($sections as $sectionKey => $sectionPaginator)
        @include('horizon.jobs.partials.index.list-one-collapsible', [
            'section' => $sectionKey,
            'paginator' => $sectionPaginator,
            'showServiceColumn' => $showServiceColumn,
            'pageService' => $pageService,
            'id' => "$resizablePrefix-$sectionKey",
            'bodyKey' => "$resizablePrefix-$sectionKey",
            'defer' => $defer,
        ])
    @endforeach
</div>
