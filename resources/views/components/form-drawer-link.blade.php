@props([
    'href',
    'variant' => 'default',
])

@php
    $variantClasses = match ($variant) {
        'destructive' => 'btn-danger',
        'outline', 'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };
    $attributes = $attributes->merge(['class' => trim("$variantClasses btn-loadable")]);
@endphp

<a
    href="{{ $href }}"
    data-turbo-frame="form-drawer"
    {{ $attributes }}
>
    <span class="btn-label">{{ $slot }}</span>
    <span class="btn-spinner" aria-hidden="true">
        <x-loader />
    </span>
</a>
