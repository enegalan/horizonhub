@php
    $showRetry ??= false;
@endphp
<div class="flex items-center gap-1">
    @if($showRetry)
        <div
            class="inline-flex"
            x-data="{
                retryUrl: {!! \Illuminate\Support\Js::from(route('horizon.jobs.retry', ['uuid' => $job->uuid, 'service_id' => $job->service->id])) !!},
                retrying: false,
                retry() {
                    if (!window.horizon || !window.horizon.http || this.retrying) return;
                    var self = this;
                    this.retrying = true;
                    window.horizon.http.post(this.retryUrl, {}).then(function () {
                        window.toast.info('Retry requested.');
                    }).catch(function () {
                    }).finally(function () {
                        self.retrying = false;
                    });
                },
            }"
        >
            <x-button
                type="button"
                variant="secondary"
                class="h-8 min-h-8 p-2 rounded-md relative"
                aria-label="Retry"
                title="Retry"
                x-bind:disabled="retrying"
                @click="retry()"
            >
                <span x-show="!retrying">
                    <x-icons.arrow-path class="size-4" />
                </span>
                <span x-cloak x-show="retrying" style="display: none" class="inline-flex items-center" aria-hidden="true">
                    <x-loader class="size-4" />
                </span>
            </x-button>
        </div>
    @endif
    <x-button
        variant="secondary"
        class="h-8 min-h-8 p-2 rounded-md"
        aria-label="View"
        title="View"
        onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->uuid]) }}'"
    >
        <x-icons.eye class="size-4" />
    </x-button>
    @include('horizon.partials.open-in-horizon-button', [
        'job' => $job,
        'service' => $pageService ?? ($job->service ?? null),
    ])
</div>
