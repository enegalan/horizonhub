@if($job->service && $job->service->getBaseUrl() && $job->status === 'failed')
    <x-button
        type="button"
        class="job-detail-retry-trigger h-8 min-h-8 p-2 relative"
        aria-label="Retry"
        title="Retry"
        data-job-detail-retry="1"
    >
        <x-heroicon-o-arrow-path class="size-4" />
    </x-button>
@endif
@php
    $horizonJobUrl = \App\Support\Horizon\JobDashboardUrlBuilder::build(
        $job->service,
        $job->uuid,
        $job->status
    );
@endphp
@if($horizonJobUrl)
    <x-button
        type="button"
        variant="secondary"
        class="h-8 min-h-8 px-3 inline-flex items-center gap-1"
        aria-label="Open in Horizon dashboard"
        title="Open in Horizon dashboard"
        onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
    >
        <x-heroicon-o-window class="size-4" />
        <span class="text-xs font-medium">Open in Horizon</span>
    </x-button>
@endif
