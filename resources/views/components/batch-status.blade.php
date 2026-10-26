@props([
    'status' => '',
])

@php
    $status = (string) $status;

    $tone = match ($status) {
        'finished' => 'badge-success',
        'failed' => 'badge-danger',
        'cancelled' => 'badge-warning',
        'pending' => 'badge-muted',
        default => 'badge-muted',
    };
    $label = match ($status) {
        'finished' => 'Finished',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
        default => 'Unknown',
    };
@endphp

<span {{ $attributes->merge(['class' => $tone]) }}>{{ $label }}</span>
