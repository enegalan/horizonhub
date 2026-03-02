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
                        class="h-9 text-sm"
                    >
                        {{ $confirmText }}
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

