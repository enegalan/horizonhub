@props([
    'eyebrow',
    'title',
    'description' => null,
    'compact' => false,
])

@php
    $paddingClass = $compact ? 'px-3 py-3 sm:px-6 sm:py-5' : 'px-5 py-5 sm:px-6';
    $blurSize = $compact ? 'size-36' : 'size-40';
@endphp

<div {{ $attributes->class(['relative border-b border-border bg-gradient-to-br from-primary/10 via-card to-card', $paddingClass]) }}>
    <div class="pointer-events-none absolute -right-10 -top-10 {{ $blurSize }} rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
    <div class="relative flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ $eyebrow }}</p>
            <h2 class="text-section-title text-foreground">{{ $title }}</h2>
            @if($description)
                <p class="max-w-2xl text-sm text-muted-foreground">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
