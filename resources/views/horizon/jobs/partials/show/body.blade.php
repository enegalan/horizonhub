@include('horizon.jobs.partials.show.breadcrumbs')
<div data-horizon-job-detail-card="1" @class(['card space-y-4 p-4'])>
    <div id="horizon-job-detail-actions">
        <div
            x-bind:class="{
                'pointer-events-none opacity-50': retrying,
                '[&_.job-detail-retry-trigger_svg]:animate-spin': retrying,
            }"
            x-bind:aria-busy="retrying"
            @click="if ($event.target.closest('[data-job-detail-retry]')) retry()"
        >
            <div class="flex flex-wrap gap-2" id="horizon-job-detail-actions-stream">
                @if(!empty($defer))
                    @include('horizon.jobs.partials.show.skeletons.actions')
                @else
                    @include('horizon.jobs.partials.show.actions')
                @endif
            </div>
        </div>
    </div>
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" id="horizon-job-detail-meta">
        @if(!empty($defer))
            @include('horizon.jobs.partials.show.skeletons.meta')
        @else
            @include('horizon.jobs.partials.show.meta')
        @endif
    </dl>
    <div id="horizon-job-detail-exception">
        @if(empty($defer))
            @include('horizon.jobs.partials.show.exception')
        @endif
    </div>
    <div id="horizon-job-detail-context">
        @if(empty($defer))
            @include('horizon.jobs.partials.show.context')
        @endif
    </div>
    <div id="horizon-job-detail-retry-history">
        @if(empty($defer))
            @include('horizon.jobs.partials.show.retry-history')
        @endif
    </div>
    <div id="horizon-job-detail-data">
        @if(!empty($defer))
            @include('horizon.jobs.partials.show.skeletons.data')
        @else
            @include('horizon.jobs.partials.show.data')
        @endif
    </div>
    <div id="horizon-job-detail-payload">
        @if(!empty($defer))
            @include('horizon.jobs.partials.show.skeletons.payload')
        @else
            @include('horizon.jobs.partials.show.payload')
        @endif
    </div>
</div>
