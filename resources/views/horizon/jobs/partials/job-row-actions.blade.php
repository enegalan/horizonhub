@php
    $serviceForDashboard = $pageService ?? ($job->service ?? null);
    $dashboardBase = $serviceForDashboard
        ? ($serviceForDashboard->public_url ?: $serviceForDashboard->base_url)
        : null;
    $jobUuidForDashboard = $job->uuid ?? null;
    $horizonDashboardPath = \rtrim(\config('horizonhub.horizon_paths.dashboard'), '/');
    $horizonJobUrl = null;
    if ($dashboardBase && $jobUuidForDashboard) {
        $horizonJobUrl = \rtrim($dashboardBase, '/') . $horizonDashboardPath . '/jobs/' . \urlencode((string) $jobUuidForDashboard);
    }
@endphp
@php
    $showRetry = $showRetry ?? false;
@endphp
<div class="flex items-center gap-1">
    @if($showRetry)
        <div
            class="inline-flex"
            x-data='window.horizonJobRowRetry(@json(["retryUrl" => route("horizon.jobs.retry", ["uuid" => $job->uuid])]))'
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
                <span x-show="retrying" class="inline-flex items-center" aria-hidden="true">
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
