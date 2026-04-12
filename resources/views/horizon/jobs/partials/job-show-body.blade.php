@include('horizon.jobs.partials.job-show-breadcrumbs')
<div class="card space-y-4 p-4">
    <div id="horizon-job-detail-actions">
        @include('horizon.jobs.partials.job-show-stream-actions')
    </div>
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" id="horizon-job-detail-meta">
        @include('horizon.jobs.partials.job-show-stream-meta')
    </dl>
    <div id="horizon-job-detail-exception">
        @include('horizon.jobs.partials.job-show-stream-exception')
    </div>
    <div id="horizon-job-detail-context">
        @include('horizon.jobs.partials.job-show-stream-context')
    </div>
    <div id="horizon-job-detail-retry-history">
        @include('horizon.jobs.partials.job-show-stream-retry-history')
    </div>
    <div id="horizon-job-detail-data">
        @include('horizon.jobs.partials.job-show-stream-data')
    </div>
    <div id="horizon-job-detail-payload">
        @include('horizon.jobs.partials.job-show-stream-payload')
    </div>
</div>
