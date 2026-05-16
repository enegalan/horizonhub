@props([
    'label',
    'tone' => 'neutral',
    'value' => null,
    'valueId' => null,
])

@php
    $toneClasses = [
        'amber' => [
            'border' => 'border-amber-500/20 bg-amber-500/5',
            'label' => 'text-amber-700 dark:text-amber-300',
        ],
        'emerald' => [
            'border' => 'border-emerald-500/20 bg-emerald-500/5',
            'label' => 'text-emerald-700 dark:text-emerald-300',
        ],
        'neutral' => [
            'border' => 'border-border/70 bg-muted/20',
            'label' => 'text-muted-foreground',
        ],
        'rose' => [
            'border' => 'border-rose-500/20 bg-rose-500/5',
            'label' => 'text-rose-700 dark:text-rose-300',
        ],
        'sky' => [
            'border' => 'border-sky-500/20 bg-sky-500/5',
            'label' => 'text-sky-700 dark:text-sky-300',
        ],
        'violet' => [
            'border' => 'border-violet-500/20 bg-violet-500/5',
            'label' => 'text-violet-700 dark:text-violet-300',
        ],
    ];

    $toneConfig = $toneClasses[$tone] ?? $toneClasses['neutral'];
    $hasValueSlot = ! $slot->isEmpty();
    $valueWrapperClass = 'mt-1 flex min-h-[2.5rem] items-center gap-2 text-2xl font-semibold tabular-nums text-foreground';
    if ($hasValueSlot && $tone === 'amber') {
        $valueWrapperClass .= ' text-base';
    }
@endphp

<div {{ $attributes->class(['rounded-lg border px-4 py-3', $toneConfig['border']]) }}>
    <p class="text-xs font-medium uppercase tracking-wide {{ $toneConfig['label'] }}">{{ $label }}</p>
    <div @class([$valueWrapperClass]) @if($valueId) id="{{ $valueId }}" @endif>
        @if($hasValueSlot)
            {{ $slot }}
        @else
            <span>{{ $value }}</span>
        @endif
    </div>
</div>
