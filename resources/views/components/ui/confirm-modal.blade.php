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
$sizes = array(
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
);

$sizeClass = isset($sizes[$size]) ? $sizes[$size] : $sizes['md'];

$primaryVariant = $variant === 'danger' ? 'destructive' : ($variant === 'warning' ? 'secondary' : 'primary');

$backdropAction = $backdropAction ?: $cancelAction;

$dialogTitle = $title;
@endphp

<div
    {{ $attributes->merge(array(
        'class' => 'fixed inset-0 z-50 overflow-y-auto px-4',
        'role' => 'dialog',
        'aria-modal' => 'true',
    )) }}
>
    @include('components.ui.backdrop', array('variant' => $backdropVariant, 'wireClick' => $backdropAction))

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
                    <x-ui.button
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
                            <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </x-ui.button>
                @endif

                @if($cancelAction)
                    <x-ui.button
                        type="button"
                        variant="ghost"
                        wire:click="{{ $cancelAction }}"
                        class="h-9 text-sm"
                    >
                        {{ $cancelText }}
                    </x-ui.button>
                @endif
            @endisset
        </div>
        </div>
    </div>
</div>

