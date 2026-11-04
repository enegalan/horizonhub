@include('pages.jobs.partials.show.breadcrumbs')
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
                    <x-skeleton.text class="h-8 w-8 rounded-md" />
                    <x-skeleton.text class="h-8 w-36 rounded-md" />
                @else
                    @include('pages.jobs.partials.show.actions')
                @endif
            </div>
        </div>
    </div>
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2" id="horizon-job-detail-meta">
        @if(!empty($defer))
            @for ($i = 0; $i < 10; $i++)
                <div>
                    <dt class="label-muted">
                        <x-skeleton.text class="h-3 w-20" />
                    </dt>
                    <dd class="mt-0.5">
                        <x-skeleton.text class="h-4 w-32 max-w-full" />
                    </dd>
                </div>
            @endfor
        @else
            @include('pages.jobs.partials.show.meta')
        @endif
    </dl>
    <div id="horizon-job-detail-exception">
        @if(empty($defer))
            @include('pages.jobs.partials.show.exception')
        @endif
    </div>
    <div id="horizon-job-detail-context">
        @if(empty($defer))
            @include('pages.jobs.partials.show.context')
        @endif
    </div>
    <div id="horizon-job-detail-retry-history">
        @if(empty($defer))
            @include('pages.jobs.partials.show.retry-history')
        @endif
    </div>
    <div id="horizon-job-detail-data">
        @if(!empty($defer))
            <div>
                <dt class="label-muted mb-1">
                    <x-skeleton.text class="h-3 w-12" />
                </dt>
                <div class="flex flex-col gap-2 mt-1 rounded-md border border-border bg-muted/30 p-3">
                    <x-skeleton.text class="block h-3 w-full max-w-md" />
                    <x-skeleton.text class="block h-3 w-full max-w-sm" />
                    <x-skeleton.text class="block h-3 w-full max-w-lg" />
                </div>
            </div>
        @else
            @include('pages.jobs.partials.show.data')
        @endif
    </div>
    <div id="horizon-job-detail-payload">
        @if(!empty($defer))
            <div>
                <dt class="label-muted mb-1">
                    <x-skeleton.text class="h-3 w-16" />
                </dt>
                <div class="flex flex-col gap-2 mt-1 rounded-md border border-border bg-muted/30 p-3">
                    <x-skeleton.text class="block h-3 w-full max-w-lg" />
                    <x-skeleton.text class="block h-3 w-full max-w-md" />
                    <x-skeleton.text class="block h-3 w-full max-w-sm" />
                </div>
            </div>
        @else
            @include('pages.jobs.partials.show.payload')
        @endif
    </div>
</div>
