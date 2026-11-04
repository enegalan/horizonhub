<div class="grid grid-cols-1 divide-y divide-border sm:grid-cols-3 sm:divide-x sm:divide-y-0">
    <x-stat-card label="Batches" variant="strip">
        <span>{{ number_format((int) ($batchCount ?? 0)) }}{{ ! empty($mayBeTruncated) ? '+' : '' }}</span>
    </x-stat-card>
    <x-stat-card label="Active" variant="strip" :value="number_format((int) ($activeCount ?? 0))" />
    <x-stat-card label="With failures" variant="strip" :value="number_format((int) ($failedCount ?? 0))" />
</div>
