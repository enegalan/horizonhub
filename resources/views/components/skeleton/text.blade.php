@props([
    'class' => '',
])
<span {{ $attributes->merge(['class' => "inline-block h-4 skeleton $class"]) }} aria-hidden="true"></span>
