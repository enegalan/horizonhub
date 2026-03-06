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
];

$sizeClass = isset($sizes[$size]) ? $sizes[$size] : $sizes['md'];

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
>
    @include('components.backdrop', ['variant' => $backdropVariant, 'wireClick' => $backdropAction])

    <div class="relative z-10 flex min-h-full items-center justify-center py-4">
        <div class="card w-full {{ $sizeClass }} p-4 bg-card">
        @if(isset($header))
            {{ $header }}
        @elseif($dialogTitle)
            <h2 class="text-section-title text-foreground mb-3">{{ $dialogTitle }}</h2>
        @endif

        @if(trim((string) $slot) !== '')
            <div class="mb-4">
                {{ $slot }}
            </div>
        @elseif($message)
            <p class="text-sm text-muted-foreground mb-4">{{ $message }}</p>
        @endif

        <div class="flex gap-2 pt-1">
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

