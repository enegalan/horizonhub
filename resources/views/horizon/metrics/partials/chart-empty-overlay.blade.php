@props([
    'overlayId',
    'title' => 'No data to display',
    'description' => 'Metrics appear once services are registered and processing jobs.',
])

<div
    id="{{ $overlayId }}"
    class="metrics-chart-empty absolute inset-0 rounded bg-muted/20"
    style="display: none;"
    aria-hidden="true"
>
    <x-empty-state
        :title="$title"
        :description="$description"
        class="h-full py-6"
    >
        <x-slot name="icon">
            <x-heroicon-o-chart-bar class="empty-state-icon" />
        </x-slot>
    </x-empty-state>
</div>
