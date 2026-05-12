@php
    $deliveryStats ??= ['total' => 0, 'slack' => 0, 'email' => 0];
@endphp
<div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $deliveryStats['total'] }}</p>
</div>
<div class="rounded-lg border border-violet-500/20 bg-violet-500/5 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-violet-700 dark:text-violet-300">Slack</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $deliveryStats['slack'] }}</p>
</div>
<div class="rounded-lg border border-sky-500/20 bg-sky-500/5 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">Email</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $deliveryStats['email'] }}</p>
</div>
