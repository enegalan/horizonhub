@php
    $serviceStats ??= ['total' => 0, 'online' => 0, 'offline' => 0];
@endphp
<x-stat-card label="Total" :value="$serviceStats['total']" />
<x-stat-card label="Online" :value="$serviceStats['online']" tone="emerald" />
<x-stat-card label="Offline" :value="$serviceStats['offline']" tone="amber" />
