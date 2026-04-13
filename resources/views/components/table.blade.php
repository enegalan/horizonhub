@props([
    'resizableKey' => null,
    'columnIds' => null,
    'bodyKey' => null,
    'bodyId' => null,
    'tableClass' => '',
    'theadClass' => null,
    'wrap' => true,
    'bodyAttributes' => null,
    'streamPatchChildren' => false,
])

@php
    $bodyBag = $bodyAttributes ?? new \Illuminate\View\ComponentAttributeBag();
    if ($bodyId !== null && $bodyId !== '') {
        $bodyBag = $bodyBag->merge(['id' => $bodyId]);
    }
    if ($bodyKey !== null && $bodyKey !== '') {
        $bodyBag = $bodyBag->merge(['data-table-body' => $bodyKey]);
    }
    if ($streamPatchChildren) {
        $bodyBag = $bodyBag->merge(['data-turbo-stream-patch-children' => 'true']);
    }
    $bodyBag = $bodyBag->class('divide-y divide-border');
    $tableClasses = trim("min-w-full overflow-hidden $tableClass");
@endphp

@if($wrap)
<div {{ $attributes->class('overflow-x-auto') }}>
    <table
        class="{{ $tableClasses }}"
        @if($resizableKey) data-resizable-table="{{ $resizableKey }}" @endif
        @if($columnIds) data-column-ids="{{ $columnIds }}" @endif
    >
@else
    <table
        {{ $attributes->class($tableClasses) }}
        @if($resizableKey) data-resizable-table="{{ $resizableKey }}" @endif
        @if($columnIds) data-column-ids="{{ $columnIds }}" @endif
    >
@endif
        <thead @class([$theadClass => filled($theadClass)])>
            {{ $head }}
        </thead>
        <tbody {{ $bodyBag }}>
            {{ $slot }}
        </tbody>
    </table>
@if($wrap)
</div>
@endif
