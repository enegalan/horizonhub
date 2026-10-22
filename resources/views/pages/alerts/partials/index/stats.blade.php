@php
    $alertStats ??= ['total' => 0, 'enabled' => 0, 'disabled' => 0];
@endphp
<x-stat-card label="Total" :value="$alertStats['total']" />
<x-stat-card label="Enabled" :value="$alertStats['enabled']" tone="emerald" />
<x-stat-card label="Disabled" :value="$alertStats['disabled']" tone="amber" />
