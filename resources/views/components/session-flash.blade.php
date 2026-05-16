@if($flash = session('status'))
    <x-flash-banner
        :message="$flash['message']"
        :variant="$flash['type'] ?? 'success'"
        {{ $attributes }}
    />
@endif
