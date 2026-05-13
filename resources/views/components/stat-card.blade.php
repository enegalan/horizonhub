@props([
    'label',
    'tone' => 'neutral',
    'value',
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
@endphp

<div {{ $attributes->class(['rounded-lg border px-4 py-3', $toneConfig['border']]) }}>
    <p class="text-xs font-medium uppercase tracking-wide {{ $toneConfig['label'] }}">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $value }}</p>
</div>
