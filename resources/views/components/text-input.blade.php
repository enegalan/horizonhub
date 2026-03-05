@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(array('class' => 'flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50')) }}>
