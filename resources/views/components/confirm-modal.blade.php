@props([
    'title' => null,
    'message' => null,
    'size' => 'md',
    'backdropVariant' => 'default',
])

@php
$sizes = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-2xl',
    'xxl' => 'max-w-full sm:max-w-[min(70rem,calc(100vw-2rem))]',
];

$defaultSize = 'md';

$size ??= $defaultSize;

$sizeClass = $sizes[$size] ?? $sizes[$defaultSize];

$dialogTitle = $title;
$wideDialog = \in_array((string) $size, ['xl', 'xxl'], true);
@endphp

<div
    {{ $attributes->merge([
        'class' => 'fixed inset-0 z-50 overflow-y-auto overscroll-contain ' . ($wideDialog ? 'px-0 sm:px-4' : 'px-4'),
        'role' => 'dialog',
        'aria-modal' => 'true',
    ]) }}
    x-cloak
    x-bind:class="$el.style.display === 'none' ? 'pointer-events-none' : ''"
    x-transition:enter="transition-opacity ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-180"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @keydown.escape.window.prevent="if (getComputedStyle($el).display !== 'none') { $dispatch('close-modal'); }"
>
    <div class="pointer-events-none">
        @include('components.backdrop', ['variant' => $backdropVariant])
    </div>

    <div
        class="relative z-10 flex min-h-full justify-center {{ $wideDialog ? 'items-stretch p-0 sm:items-center sm:p-4' : 'items-end p-3 sm:items-center sm:p-4' }}"
        @click.self="$dispatch('close-modal')"
    >
        <div
            class="card w-full {{ $sizeClass }} bg-card {{ $wideDialog ? 'flex min-h-0 max-h-[100dvh] flex-col overflow-hidden rounded-none p-3 max-sm:min-h-[100dvh] sm:max-h-[min(90vh,100dvh-2rem)] sm:rounded-lg sm:p-4' : 'p-4' }}"
        >
            @if(isset($header))
                {{ $header }}
            @elseif($dialogTitle)
                <h2 class="text-section-title text-foreground mb-3">{{ $dialogTitle }}</h2>
            @endif

            @if(trim((string) $slot) !== '')
                <div class="mt-4 {{ $wideDialog ? 'min-h-0 flex-1 overflow-y-auto overflow-x-hidden overscroll-y-contain' : 'mb-4' }}">
                    {{ $slot }}
                </div>
            @elseif($message)
                <p class="text-sm text-muted-foreground mb-4">{{ $message }}</p>
            @endif

            @isset($footer)
                <div @class([
                    'mt-1 flex shrink-0 gap-2',
                    'border-t border-border pt-3 pb-[max(0.25rem,env(safe-area-inset-bottom))]' => $wideDialog,
                    'pt-1' => ! $wideDialog,
                ])>
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
