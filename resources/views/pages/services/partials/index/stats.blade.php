@php
    $serviceStats ??= ['total' => 0, 'online' => 0, 'offline' => 0];
@endphp
<div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
    <x-stat-card label="Total" variant="strip" :value="$serviceStats['total']" />
    <x-stat-card label="Online" variant="strip" :value="$serviceStats['online']" />
    <x-stat-card label="Offline" variant="strip" :value="$serviceStats['offline']" />
</div>
