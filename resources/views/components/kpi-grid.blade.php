@props([
    'gradient' => false,
])

@if($gradient)
    <div {{ $attributes->class(['card overflow-hidden']) }}>
        <div class="relative bg-gradient-to-br from-primary/10 via-card to-card py-4">
            <div class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-primary/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative grid gap-3 px-5 py-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                {{ $slot }}
            </div>
        </div>
    </div>
@else
    <div {{ $attributes->class(['grid gap-4 sm:grid-cols-2 lg:grid-cols-4']) }}>
        {{ $slot }}
    </div>
@endif
