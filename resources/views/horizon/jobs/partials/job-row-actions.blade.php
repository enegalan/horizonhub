@php
    $serviceForDashboard = $pageService ?? ($job->service ?? null);
    $jobUuidForDashboard = $job->uuid ?? null;
    $jobStatusForDashboard = (string) ($job->status ?? '');
    $horizonJobUrl = \App\Support\Horizon\JobDashboardUrlBuilder::build(
        $serviceForDashboard,
        $jobUuidForDashboard,
        $jobStatusForDashboard
    );
@endphp
@php
    $showRetry = $showRetry ?? false;
@endphp
<div class="flex items-center gap-1">
    @if($showRetry)
        <div
            class="inline-flex"
            x-data='window.horizonJobRowRetry(@json(["retryUrl" => route("horizon.jobs.retry", ["uuid" => $job->uuid, 'service_id' => $job->service->id])]))'
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
                    <x-heroicon-o-arrow-path class="size-4" />
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
        onclick="window.location.href='{{ route('horizon.jobs.show', ['job' => $job->uuid, 'service_id' => $job->service->id]) }}'"
    >
        <x-heroicon-o-eye class="size-4" />
    </x-button>
    @if($horizonJobUrl)
        <x-button
            type="button"
            variant="ghost"
            class="h-8 min-h-8 px-2 rounded-md"
            aria-label="Open in Horizon dashboard"
            title="Open in Horizon dashboard"
            onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
        >
            <x-heroicon-o-window class="size-4" />
        </x-button>
    @endif
</div>
