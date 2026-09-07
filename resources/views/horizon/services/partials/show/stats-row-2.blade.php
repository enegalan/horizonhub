<x-stat-card label="Total processes" tone="neutral" :value="$totalProcesses !== null ? number_format($totalProcesses) : '–'" />
<x-stat-card label="Max wait time" tone="sky" :value="$maxWaitTimeSeconds !== null ? (\App\Support\Jobs\JobRuntimeHelper::getFormattedRuntime((float) $maxWaitTimeSeconds) ?? '–') : '–'" />
<x-stat-card label="Max runtime" tone="violet" :value="$queueWithMaxRuntime !== null ? $queueWithMaxRuntime : '–'" />
<x-stat-card label="Max throughput" tone="emerald" :value="$queueWithMaxThroughput !== null ? $queueWithMaxThroughput : '–'" />
