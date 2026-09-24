@props(['variant' => 'default', 'type' => 'button'])

@php
    $variantClasses = match ($variant) {
        'destructive' => 'btn-danger',
        'outline' => 'btn-secondary',
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        'none' => '',
        'link' => 'link inline-flex items-center justify-center',
        default => 'btn-primary',
    };
    $type = $attributes->get('type', $type);
    $disabled = $attributes->get('disabled', false);
    $defaultClasses = 'btn-loadable';

    $attributes = $attributes
        ->except('disabled')
        ->merge([
            'type' => $type,
            'class' => trim("$variantClasses $defaultClasses"),
            ...($disabled ? ['disabled' => true] : []),
        ]);
@endphp
<button {{ $attributes }}>
    <span class="btn-label">{{ $slot }}</span>
    <span class="btn-spinner" aria-hidden="true">
        <x-loader />
    </span>
</button>
