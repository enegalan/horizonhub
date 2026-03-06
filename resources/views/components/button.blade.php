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
@endphp

@php
    $mergedClass = trim($variantClasses . ' ' . $attributes->get('class', ''));
    $disabled = $attributes->get('disabled', false);
    $attributes = $attributes->except('disabled')->merge(['type' => $type, 'class' => $mergedClass]);
    if ($disabled) {
        $attributes = $attributes->merge(['disabled' => true]);
    }
@endphp
<button {{ $attributes }}>
    {{ $slot }}
</button>

