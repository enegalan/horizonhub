@props([
    'title' => null,
    'message' => null,
    'variant' => 'danger',
    'size' => 'md',
    'confirmText' => 'Confirm',
    'cancelText' => 'Cancel',
    'confirmAction' => null,
    'cancelAction' => null,
    'backdropAction' => null,
    'backdropVariant' => 'default',
])

@php
$sizes = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-2xl',
];

$defaultSize = 'md';

$size ??= $defaultSize;

$sizeClass = $sizes[$size] ?? $sizes[$defaultSize];

$primaryVariant = $variant === 'danger' ? 'destructive' : ($variant === 'warning' ? 'secondary' : 'primary');

$backdropAction = $backdropAction ?: $cancelAction;

$dialogTitle = $title;
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
>
    <div>
        @include('components.backdrop', ['variant' => $backdropVariant, 'wireClick' => $backdropAction])
    </div>

    <div class="relative z-10 flex min-h-full items-center justify-center py-4">
        <div
            class="card w-full {{ $sizeClass }} p-4 bg-card {{ $size === 'xl' ? 'max-h-[90vh] flex flex-col overflow-hidden' : '' }}"
        >
        @if(isset($header))
            {{ $header }}
        @elseif($dialogTitle)
            <h2 class="text-section-title text-foreground mb-3">{{ $dialogTitle }}</h2>
        @endif

        @if(trim((string) $slot) !== '')
            <div class="{{ $size === 'xl' ? 'min-h-0 flex-1 overflow-hidden mb-4' : 'mb-4' }}">
                {{ $slot }}
            </div>
        @elseif($message)
            <p class="text-sm text-muted-foreground mb-4">{{ $message }}</p>
        @endif

        <div class="flex shrink-0 gap-2 pt-1">
            @isset($footer)
                {{ $footer }}
            @else
                @if($confirmAction)
                    <x-button
                        type="button"
                        variant="{{ $primaryVariant }}"
                        wire:click="{{ $confirmAction }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $confirmAction }}"
                        class="h-9 text-sm relative inline-flex items-center justify-center"
                    >
                        <span wire:loading.remove wire:target="{{ $confirmAction }}">
                            {{ $confirmText }}
                        </span>
                        <span wire:loading wire:target="{{ $confirmAction }}" class="inline-flex" aria-hidden="true">
                            <x-loader />
                        </span>
                    </x-button>
                @endif

                @if($cancelAction)
                    <x-button
                        type="button"
                        variant="ghost"
                        wire:click="{{ $cancelAction }}"
                        class="h-9 text-sm"
                    >
                        {{ $cancelText }}
                    </x-button>
                @endif
            @endisset
        </div>
        </div>
    </div>
</div>
