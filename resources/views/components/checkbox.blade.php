@props(['disabled' => false])

@php
    $inputAttrs = $attributes->merge([
        'type' => 'checkbox',
        'class' => 'peer sr-only',
    ]);
    if ($disabled) {
        $inputAttrs = $inputAttrs->merge(['disabled' => true]);
    }
@endphp
<div
    class="checkbox-root"
    x-data="{ mouseDownOnCheckbox: false }"
    x-ref="checkboxWrap"
    @mouseup.window="if (mouseDownOnCheckbox && $refs.checkboxWrap && !$refs.checkboxWrap.contains($event.target)) { $refs.checkboxWrap.querySelector('input')?.focus(); } mouseDownOnCheckbox = false"
>
    <label
        @mousedown="mouseDownOnCheckbox = true"
        class="inline-flex cursor-pointer items-center gap-2 [&:has(input:disabled)]:cursor-not-allowed [&:has(input:disabled)]:opacity-70"
    >
        <span class="relative inline-flex h-4 w-4 shrink-0">
            <input {{ $inputAttrs }} />
            <span checkbox class="absolute inset-0 rounded-full border border-input bg-background shadow-sm transition-colors peer-disabled:pointer-events-none peer-disabled:opacity-50 peer-checked:border-primary peer-checked:bg-primary"></span>
            <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity peer-checked:opacity-100 text-primary-foreground pointer-events-none">
                <x-heroicon-o-check class="h-3 w-3" />
            </span>
        </span>
        @if(isset($slot) && trim($slot) !== '')
            <span class="text-sm font-normal select-none">{{ $slot }}</span>
        @endif
    </label>
</div>

