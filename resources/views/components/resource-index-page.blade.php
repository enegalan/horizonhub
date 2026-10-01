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

    <div @class([
        'grid gap-3 border-b border-border px-5 py-4 sm:px-6',
        'sm:grid-cols-2' => (int) $statsColumns === 2,
        'sm:grid-cols-3' => (int) $statsColumns === 3,
        'sm:grid-cols-4' => (int) $statsColumns === 4,
    ])>
        <div
            @if($statsTarget) id="{{ $statsTarget }}" @endif
            class="contents"
            data-turbo-stream-patch-children="true"
        >
            @if($defer)
                <x-dynamic-component :component="$statsSkeleton" :columns="$statsColumns" />
            @else
                @include($statsView, $statsViewData)
            @endif
        </div>
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
