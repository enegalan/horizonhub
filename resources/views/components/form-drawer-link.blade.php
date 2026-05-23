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
@endphp

<a
    href="{{ $href }}"
    data-turbo-frame="form-drawer"
    {{ $attributes->merge(['class' => trim($variantClasses . ' ' . $attributes->get('class', ''))]) }}
>{{ $slot }}</a>
