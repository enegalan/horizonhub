<x-stat-card label="Batches" tone="violet">
    <span>{{ number_format((int) ($batchCount ?? 0)) }}{{ ! empty($mayBeTruncated) ? '+' : '' }}</span>
</x-stat-card>
<x-stat-card label="Active" tone="sky" :value="number_format((int) ($activeCount ?? 0))" />
<x-stat-card label="With failures" tone="rose" :value="number_format((int) ($failedCount ?? 0))" />
