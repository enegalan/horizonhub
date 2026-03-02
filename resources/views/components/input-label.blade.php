@props(['value'])

<label {{ $attributes->merge(array('class' => 'block text-sm font-medium leading-none text-muted-foreground peer-disabled:opacity-70')) }}>
    {{ $value ?? $slot }}
</label>
