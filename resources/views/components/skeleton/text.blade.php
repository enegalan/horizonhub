@props([
    'class' => '',
])
<span {{ $attributes->merge(['class' => "inline-block h-4 animate-pulse rounded-md bg-muted $class"]) }} aria-hidden="true"></span>
