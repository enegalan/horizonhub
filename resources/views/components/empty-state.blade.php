@props([
    'title',
    'description' => null,
])

<div {{ $attributes->class(['empty-state']) }}>
    @isset($icon)
        {{ $icon }}
    @endisset
    <p class="empty-state-title">{{ $title }}</p>
    @if($description)
        <p class="empty-state-description">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
