@php
    $alertStats ??= ['total' => 0, 'enabled' => 0, 'disabled' => 0];
@endphp
<div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
    <x-stat-card label="Total" variant="strip" :value="$alertStats['total']" />
    <x-stat-card label="Enabled" variant="strip" :value="$alertStats['enabled']" />
    <x-stat-card label="Disabled" variant="strip" :value="$alertStats['disabled']" />
</div>
