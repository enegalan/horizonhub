@props([
    'message',
    'variant' => 'success',
])

@php
    $variantConfig = match ($variant) {
        'error' => [
            'container' => 'border-rose-500/30 bg-rose-500/10 text-rose-900 dark:text-rose-200',
            'icon' => 'heroicon-o-x-circle',
            'iconClass' => 'text-rose-600 dark:text-rose-400',
            'buttonClass' => 'text-rose-800 hover:bg-rose-500/15 dark:text-rose-300',
            'role' => 'alert',
        ],
        'warning' => [
            'container' => 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200',
            'icon' => 'heroicon-o-exclamation-triangle',
            'iconClass' => 'text-amber-600 dark:text-amber-400',
            'buttonClass' => 'text-amber-800 hover:bg-amber-500/15 dark:text-amber-300',
            'role' => 'status',
        ],
        default => [
            'container' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-200',
            'icon' => 'icons.check-circle',
            'iconClass' => 'text-emerald-600 dark:text-emerald-400',
            'buttonClass' => 'text-emerald-800 hover:bg-emerald-500/15 dark:text-emerald-300',
            'role' => 'status',
        ],
    };
@endphp

<div
    {{ $attributes->class([
        'flex items-center gap-3 border-b px-5 py-3 text-sm sm:px-6',
        $variantConfig['container'],
    ]) }}
    x-data="{ visible: true }"
    x-show="visible"
    x-cloak
    role="{{ $variantConfig['role'] }}"
>
    <x-dynamic-component :component="$variantConfig['icon']" @class(['size-5 shrink-0', $variantConfig['iconClass']]) />
    <p class="min-w-0 flex-1 break-words">{{ $message }}</p>
    <button
        type="button"
        class="btn-ghost -mr-1 inline-flex size-8 shrink-0 items-center justify-center rounded-md {{ $variantConfig['buttonClass'] }}"
        aria-label="Dismiss"
        @click="visible = false"
    >
        <x-heroicon-o-x-mark class="size-4" />
    </button>
</div>
