@props([
    'title',
    'eyebrow' => null,
    'description' => null,
    'filterAction' => null,
    'filterFormAttributes' => null,
    'statsTarget' => null,
    'statsColumns' => 3,
    'statsView' => null,
    'statsViewData' => [],
    'statsSkeleton' => 'skeleton.metric-columns',
    'bodyTarget' => null,
    'bodyView' => null,
    'bodyViewData' => [],
    'bodyColumns' => 'sm:grid-cols-2 xl:grid-cols-3',
    'defer' => false,
])

@php
    $formAttributes = \is_string($filterFormAttributes)
        ? new \Illuminate\Support\HtmlString($filterFormAttributes)
        : new \Illuminate\View\ComponentAttributeBag((array) $filterFormAttributes);
@endphp

<div class="card overflow-hidden">
    <x-page-hero :eyebrow="$eyebrow" :title="$title" :description="$description">
        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    </x-page-hero>

    <div class="border-b border-border bg-muted/15 px-5 py-4 sm:px-6">
        <form
            method="GET"
            @if($filterAction) action="{{ $filterAction }}" @endif
            class="flex flex-wrap items-end gap-3"
            data-turbo-frame="_top"
            {{ $formAttributes }}
        >
            {{ $filter ?? '' }}
            <x-button type="submit" class="h-9 w-full shrink-0 text-sm sm:w-auto">
                Search
            </x-button>
        </form>
    </div>

    <div
        @if($statsTarget) id="{{ $statsTarget }}" @endif
        class="border-b border-border"
        data-turbo-stream-patch-children="true"
    >
        @if($defer)
            <div @class([
                'grid grid-cols-1 divide-y divide-border',
                'sm:grid-cols-2 sm:divide-x' => (int) $statsColumns === 2,
                'sm:grid-cols-3 sm:divide-x sm:divide-y-0' => (int) $statsColumns === 3,
                'sm:grid-cols-2 sm:divide-x lg:grid-cols-4 lg:divide-y-0' => (int) $statsColumns === 4,
            ])>
                <x-skeleton.metric-columns :columns="$statsColumns" variant="strip" />
            </div>
        @else
            @include($statsView, $statsViewData)
        @endif

    </div>

    @isset($divider)
        {{ $divider }}
    @endisset

    <div class="px-5 py-5 sm:px-6">
        @isset($body)
            {{ $body }}
        @else
            <div
                @if($bodyTarget) id="{{ $bodyTarget }}" @endif
                class="grid gap-4 {{ $bodyColumns }}"
                data-turbo-stream-patch-children="true"
            >
                @if($defer)
                    <x-skeleton.card-grid />
                @else
                    @include($bodyView, $bodyViewData)
                @endif
            </div>
        @endisset
    </div>
</div>
