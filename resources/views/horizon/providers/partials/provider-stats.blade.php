@php
    $deliveryStats ??= ['total' => 0, 'slack' => 0, 'email' => 0];
@endphp
<x-stat-card label="Total" :value="$deliveryStats['total']" />
<x-stat-card label="Slack" :value="$deliveryStats['slack']" tone="violet" />
<x-stat-card label="Email" :value="$deliveryStats['email']" tone="sky" />
