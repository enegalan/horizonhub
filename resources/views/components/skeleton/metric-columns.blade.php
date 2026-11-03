@props([
    'columns' => 3,
    'variant' => 'quiet',
])

@php
    $variant = $variant === 'strip' ? 'strip' : 'quiet';
@endphp

@for ($i = 0; $i < (int) $columns; $i++)
    @if($variant === 'strip')
        <div class="animate-pulse space-y-2 px-4 py-4 text-center sm:px-5">
            <div class="mx-auto h-3 w-24 rounded bg-muted"></div>
            <div class="mx-auto h-8 w-12 rounded bg-muted"></div>
        </div>
    @else
        <div class="rounded-lg border border-border/80 bg-card px-4 py-3">
            <div class="skeleton h-3 w-20" style="--skeleton-delay: {{ $i * 80 }}ms"></div>
            <div class="skeleton mt-3 h-7 w-12" style="--skeleton-delay: {{ ($i * 80) + 120 }}ms"></div>
        </div>
    @endif
@endfor
