<div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Jobs past minute</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastMinute) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Jobs past hour</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($jobsPastHour) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Failed jobs (past 7 days)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($failedPastSevenDays) }}</p>
        </div>
        <div class="card p-4">
            <h3 class="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">Processed (24h)</h3>
            <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format($processedPast24Hours) }}</p>
        </div>
    </div>

    <div class="grid gap-4">
        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-1">Failure rate (last 24h)</h3>
            <p class="text-xl font-semibold text-foreground">{{ $failureRate24h['rate'] }}% <span class="text-xs font-normal text-muted-foreground">({{ $failureRate24h['failed'] }} failed / {{ $failureRate24h['processed'] }} processed)</span></p>
        </div>

        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Processed vs failed (last 24h, by hour)</h3>
            <div id="processed-failed-chart" class="h-56"></div>
        </div>

        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Failure rate over time (last 24h, %)</h3>
            <div id="failure-rate-chart" class="h-56"></div>
        </div>

        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Average job runtime (last 24h, seconds)</h3>
            <div id="runtime-chart" class="h-56"></div>
        </div>

        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Jobs by queue (last 7 days, top 12)</h3>
            <div id="queue-distribution-chart" class="h-80"></div>
        </div>

        <div class="card p-4" wire:ignore>
            <h3 class="text-section-title text-foreground mb-2">Jobs by service (last 7 days, top 10)</h3>
            <div id="service-distribution-chart" class="h-80"></div>
        </div>

        <div class="card p-4">
            <h3 class="text-section-title text-foreground mb-2">Failed by service × queue (past 7 days, top 15)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="table-header px-4 py-2.5 text-left">Service</th>
                            <th class="table-header px-4 py-2.5 text-left">Queue</th>
                            <th class="table-header px-4 py-2.5 text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($failuresTable as $row)
                            <tr class="hover:bg-muted/30">
                                <td class="px-4 py-2.5 font-medium text-foreground">{{ $row['service'] }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground">{{ $row['queue'] }}</td>
                                <td class="px-4 py-2.5 text-right text-muted-foreground">{{ number_format($row['cnt']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-muted-foreground text-sm">No failures in the past 7 days</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script type="application/json" id="metrics-chart-data">@json($metricsChartData)</script>
</div>

@script
<script>
    window.addEventListener('horizon-hub-refresh', () => {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
