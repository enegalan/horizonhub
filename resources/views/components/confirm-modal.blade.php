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
    'xxl' => 'max-w-[min(70rem,calc(100vw-2rem))]',
];

$defaultSize = 'md';

$size ??= $defaultSize;

$sizeClass = $sizes[$size] ?? $sizes[$defaultSize];

$dialogTitle = $title;
$wideDialog = \in_array((string) $size, ['xl', 'xxl'], true);
@endphp

<div
    {{ $attributes->merge([
        'class' => 'fixed inset-0 z-50 overflow-y-auto px-4',
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

    <div class="relative z-10 flex min-h-full items-center justify-center py-4" @click.self="$dispatch('close-modal')">
        <div
            class="card w-full {{ $sizeClass }} p-4 bg-card {{ $wideDialog ? 'max-h-[90vh] flex flex-col overflow-hidden' : '' }}"
        >
        @if(isset($header))
            {{ $header }}
        @elseif($dialogTitle)
            <h2 class="text-section-title text-foreground mb-3">{{ $dialogTitle }}</h2>
        @endif

        @if(trim((string) $slot) !== '')
            <div class="{{ $wideDialog ? 'min-h-0 flex-1 overflow-hidden mb-4' : 'mb-4' }}">
                {{ $slot }}
            </div>
        @elseif($message)
            <p class="text-sm text-muted-foreground mb-4">{{ $message }}</p>
        @endif

        @isset($footer)
            <div class="flex shrink-0 gap-2 pt-1">
                {{ $footer }}
            </div>
        @endisset
        </div>
    </div>
</div>
