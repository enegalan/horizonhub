@props([
    'column' => null,
    'padding' => 'px-4 py-2.5',
])

<th
    {{ $attributes->class(['table-header', $padding]) }}
    @if ($column !== null) data-column-id="{{ $column }}" @endif
>
    {{ $slot }}
</th>
