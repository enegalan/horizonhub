@php
    /** @var bool $showServiceColumn */
    /** @var \App\Models\Service|null $pageService */
    /** @var string $columnIds */
    /** @var string $resizablePrefix */
@endphp
<div
    class="rounded-bl-[var(--radius)] overflow-hidden"
    id="horizon-jobs-stack"
    x-data="{
        sectionOpen: (() => {
            const fallback = { processing: true, processed: true, failed: true };
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
    @include('horizon.jobs.partials.job-list-one-collapsible', [
        'kind' => 'processing',
        'paginator' => $jobsProcessing,
        'showServiceColumn' => $showServiceColumn,
        'pageService' => $pageService,
        'resizableKey' => "$resizablePrefix-processing",
        'bodyKey' => "$resizablePrefix-processing",
        'columnIds' => $columnIds,
    ])
    @include('horizon.jobs.partials.job-list-one-collapsible', [
        'kind' => 'processed',
        'paginator' => $jobsProcessed,
        'showServiceColumn' => $showServiceColumn,
        'pageService' => $pageService,
        'resizableKey' => "$resizablePrefix-processed",
        'bodyKey' => "$resizablePrefix-processed",
        'columnIds' => $columnIds,
    ])
    @include('horizon.jobs.partials.job-list-one-collapsible', [
        'kind' => 'failed',
        'paginator' => $jobsFailed,
        'showServiceColumn' => $showServiceColumn,
        'pageService' => $pageService,
        'resizableKey' => "$resizablePrefix-failed",
        'bodyKey' => "$resizablePrefix-failed",
        'columnIds' => $columnIds,
    ])
</div>
