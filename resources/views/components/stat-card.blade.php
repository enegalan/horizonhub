@props([
    'label',
    'value' => null,
    'valueId' => null,
    'variant' => 'quiet',
])

@php
    $hasValueSlot = ! $slot->isEmpty();
    $variant = $variant === 'strip' ? 'strip' : 'quiet';
@endphp

@if($variant === 'strip')
    <div {{ $attributes->class(['px-4 py-4 text-center sm:px-5']) }}>
        <p class="text-sm text-muted-foreground">{{ $label }}</p>
        <div
            class="mt-1 flex min-h-8 items-center justify-center gap-2 text-3xl font-semibold tabular-nums tracking-tight text-foreground"
            @if($valueId) id="{{ $valueId }}" @endif
        >
            @if($hasValueSlot)
                {{ $slot }}
            @else
                <span>{{ $value }}</span>
            @endif
        </div>
    </div>
@else
    <div {{ $attributes->class(['rounded-lg border border-border/80 bg-card px-4 py-3']) }}>
        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $label }}</p>
        <div
            class="mt-1 flex min-h-10 items-center gap-2 text-2xl font-semibold tabular-nums tracking-tight text-foreground"
            @if($valueId) id="{{ $valueId }}" @endif
        >
            @if($hasValueSlot)
                {{ $slot }}
            @else
                <span>{{ $value }}</span>
            @endif
        </div>
    </div>
@endif
