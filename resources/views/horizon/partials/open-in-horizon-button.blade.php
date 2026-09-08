@php
    $horizonJobUrl = \App\Support\Jobs\JobDashboardUrlBuilder::build(
        $service ?? ($job->service ?? null),
        $job->uuid,
        $job->status,
    );
    $buttonVariant ??= 'ghost';
    $buttonClass ??= 'h-8 min-h-8 px-2 rounded-md';
    $showLabel ??= false;
@endphp
@if($horizonJobUrl)
    <x-button
        type="button"
        variant="{{ $buttonVariant }}"
        class="{{ $buttonClass }}"
        aria-label="Open in Horizon dashboard"
        title="Open in Horizon dashboard"
        onclick="try { window.open('{{ $horizonJobUrl }}', '_blank'); } catch (e) {}"
    >
        <x-icons.window class="size-4" />
        @if($showLabel)
            <span class="text-xs font-medium">Open in Horizon</span>
        @endif
    </x-button>
@endif
