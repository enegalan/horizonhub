@php
    $alertStats ??= ['total' => 0, 'enabled' => 0, 'disabled' => 0];
@endphp
<div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $alertStats['total'] }}</p>
</div>
<div class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Enabled</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $alertStats['enabled'] }}</p>
</div>
<div class="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
    <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Disabled</p>
    <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">{{ $alertStats['disabled'] }}</p>
</div>
