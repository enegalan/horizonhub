@props(['disabled' => false])

@php
    $inputAttrs = $attributes->merge(array(
        'type' => 'checkbox',
        'class' => 'peer sr-only',
    ));
    if ($disabled) {
        $inputAttrs = $inputAttrs->merge(array('disabled' => true));
    }
@endphp
<label class="inline-flex cursor-pointer items-center gap-2 [&:has(input:disabled)]:cursor-not-allowed [&:has(input:disabled)]:opacity-70">
    <span class="relative inline-flex h-4 w-4 shrink-0">
        <input {{ $inputAttrs }} />
        <span class="absolute inset-0 rounded-[var(--radius)] border border-input bg-background shadow-sm transition-colors peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2 peer-disabled:pointer-events-none peer-disabled:opacity-50 peer-checked:border-primary peer-checked:bg-primary"></span>
        <span class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity peer-checked:opacity-100 text-primary-foreground pointer-events-none">
            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
        </span>
    </span>
    @if(isset($slot) && trim($slot) !== '')
        <span class="text-sm font-normal select-none">{{ $slot }}</span>
    @endif
</label>
