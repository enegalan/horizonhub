@php
    $providerCount = \is_countable($deliveryStats ?? null) ? \count($deliveryStats) : 4;
@endphp
<div @class([
    'grid grid-cols-1 divide-y divide-border sm:divide-x sm:divide-y-0',
    'sm:grid-cols-2' => $providerCount === 2,
    'sm:grid-cols-3' => $providerCount === 3,
    'sm:grid-cols-2 lg:grid-cols-4' => $providerCount >= 4,
])>
    @foreach($deliveryStats as $providerType => $count)
        <x-stat-card label="{{ ucfirst($providerType) }}" variant="strip" :value="$count" />
    @endforeach
</div>
