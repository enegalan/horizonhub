@props([
    'id' => null,
    'tableClass' => '',
    'theadClass' => null,
    'wrap' => true,
    'bodyAttributes' => null,
    'streamPatchChildren' => false,
])

@php
    $bodyBag = $bodyAttributes ?? new \Illuminate\View\ComponentAttributeBag();
    if (!empty($id)) {
        $bodyBag = $bodyBag->merge(['id' => "tbody-$id"]);
    }
    if ($streamPatchChildren) {
        $bodyBag = $bodyBag->merge(['data-turbo-stream-patch-children' => 'true']);
    }
    $bodyBag = $bodyBag->class('divide-y divide-border');
    $tableClasses = trim("min-w-full w-full overflow-hidden $tableClass");
@endphp

@if($wrap)
<div {{ $attributes->class('table-scroll') }}>
    <table
        class="{{ $tableClasses }}"
        @if($id) data-resizable-table="{{ $id }}" @endif
    >
@else
    <table
        {{ $attributes->class($tableClasses) }}
        @if($id) data-resizable-table="{{ $id }}" @endif
    >
@endif
        <thead @class([$theadClass => filled($theadClass)])>
            <tr class="border-b border-t border-border bg-muted/50">
                {{ $head }}
            </tr>
        </thead>
        <tbody {{ $bodyBag }}>
            {{ $slot }}
        </tbody>
    </table>
@if($wrap)
</div>
@endif
