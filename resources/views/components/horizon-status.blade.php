@props([
    'status' => '',
])

@php
    $status = (string) $status;
    $normalized = \strtolower($status);

    $tone = match (true) {
        \in_array($normalized, ['running', 'active', 'online'], true) => 'badge-success',
        \in_array($normalized, ['paused', 'inactive', 'stand_by', 'standby'], true) => 'badge-warning',
        $normalized === '' => 'badge-muted',
        default => 'badge-danger',
    };
@endphp

<span {{ $attributes->merge(['class' => $tone]) }}>{{ $status !== '' ? \ucfirst($status) : 'Unknown' }}</span>
