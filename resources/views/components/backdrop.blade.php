@php
$variant ??= 'default';
$style = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:0;backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);';
$style .= ($variant === 'muted')
    ? 'background:hsl(var(--foreground) / 0.1);'
    : 'background:rgba(0,0,0,0.55);';
$extraAttrs ??= '';
@endphp
<div
    style="{{ $style }}"
    role="button"
    tabindex="-1"
    aria-label="Close"
    @if(!empty($wireClick)) wire:click="{{ $wireClick }}" @endif
    {!! $extraAttrs !!}
></div>
