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
        sectionOpen: { processing: true, processed: true, failed: true },
        init() {
            try {
                const s = localStorage.getItem('horizon_jobs_sections');
                if (s) { this.sectionOpen = Object.assign({}, this.sectionOpen, JSON.parse(s)); }
            } catch (e) {}
        },
        onToggle(section, e) {
            this.sectionOpen[section] = e.target.open;
            localStorage.setItem('horizon_jobs_sections', JSON.stringify(this.sectionOpen));
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
