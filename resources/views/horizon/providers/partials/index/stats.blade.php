@php
    $deliveryStats ??= ['total' => 0, 'slack' => 0, 'email' => 0, 'discord' => 0];
@endphp
<x-stat-card label="Total" :value="$deliveryStats['total']" />
<x-stat-card label="Slack" :value="$deliveryStats['slack']" tone="violet" />
<x-stat-card label="Discord" :value="$deliveryStats['discord']" class="border-indigo-500/20 bg-indigo-500/5 [&>p:first-child]:text-indigo-700 dark:[&>p:first-child]:text-indigo-300" />
<x-stat-card label="Email" :value="$deliveryStats['email']" tone="sky" />
