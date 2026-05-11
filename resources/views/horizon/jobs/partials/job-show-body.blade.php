@include('horizon.jobs.partials.job-show-breadcrumbs')
<div data-horizon-job-detail-card="1" @class(['card space-y-4 p-4', 'motion-safe:animate-pulse' => !empty($defer)])>
    <div id="horizon-job-detail-actions">
        <div class="flex flex-wrap gap-2" x-bind:class="{ 'pointer-events-none opacity-50': retrying }">
            <div id="horizon-job-detail-actions-stream">
                @if(!empty($defer))
                    @include('horizon.jobs.partials.job-show-stream-actions-skeleton')
                @else
                    @include('horizon.jobs.partials.job-show-stream-actions')
                @endif
            </div>
        </div>
    </div>
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" id="horizon-job-detail-meta">
        @if(!empty($defer))
            @include('horizon.jobs.partials.job-show-stream-meta-skeleton')
        @else
            @include('horizon.jobs.partials.job-show-stream-meta')
        @endif
    </dl>
    <div id="horizon-job-detail-exception">
        @if(empty($defer))
            @include('horizon.jobs.partials.job-show-stream-exception')
        @endif
    </div>
    <div id="horizon-job-detail-context">
        @if(empty($defer))
            @include('horizon.jobs.partials.job-show-stream-context')
        @endif
    </div>
    <div id="horizon-job-detail-retry-history">
        @if(empty($defer))
            @include('horizon.jobs.partials.job-show-stream-retry-history')
        @endif
    </div>
    <div id="horizon-job-detail-data">
        @if(!empty($defer))
            @include('horizon.jobs.partials.job-show-stream-data-skeleton')
        @else
            @include('horizon.jobs.partials.job-show-stream-data')
        @endif
    </div>
    <div id="horizon-job-detail-payload">
        @if(!empty($defer))
            @include('horizon.jobs.partials.job-show-stream-payload-skeleton')
        @else
            @include('horizon.jobs.partials.job-show-stream-payload')
        @endif
    </div>
</div>
