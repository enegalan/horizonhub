@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(array('class' => 'input-custom')) }}>
