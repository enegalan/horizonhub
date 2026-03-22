@props(['disabled' => false, 'withTime' => false, 'range' => false])

<input
    type="text"
    autocomplete="off"
    placeholder="@if ($range && $withTime){{ 'Start to end (YYYY-MM-DDTHH:mm)' }}@elseif ($range){{ 'Start to end (YYYY-MM-DD)' }}@elseif ($withTime){{ 'YYYY-MM-DDTHH:mm' }}@else{{ 'YYYY-MM-DD' }}@endif"
    @if ($range && $withTime)
        x-datepicker.range.time
    @elseif ($range)
        x-datepicker.range
    @elseif ($withTime)
        x-datepicker.time
    @else
        x-datepicker
    @endif
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'flex h-9 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm transition-colors placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
    ]) }}
>
