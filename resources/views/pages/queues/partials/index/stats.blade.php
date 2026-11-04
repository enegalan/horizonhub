<div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-2 sm:divide-x sm:divide-y-0">
    <x-stat-card label="Queues" variant="strip" :value="number_format((int) ($queueCount ?? 0))" />
    <x-stat-card label="Pending jobs" variant="strip" :value="number_format((int) ($totalJobs ?? 0))" />
</div>
