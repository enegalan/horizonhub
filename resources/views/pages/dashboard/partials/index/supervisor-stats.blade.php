@php
    $totalInstances = (int) ($supervisorStats['totalInstances'] ?? 0);
    $totalSupervisors = (int) ($supervisorStats['totalSupervisors'] ?? 0);
    $pausedSupervisors = (int) ($supervisorStats['pausedSupervisors'] ?? 0);
@endphp

<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Total Instances</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($totalInstances) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Total Supervisors</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($totalSupervisors) }}</p>
</div>
<div class="px-4 py-4 text-center sm:px-5">
    <p class="text-sm text-muted-foreground">Paused Supervisors</p>
    <p class="mt-1 text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ number_format($pausedSupervisors) }}</p>
</div>
