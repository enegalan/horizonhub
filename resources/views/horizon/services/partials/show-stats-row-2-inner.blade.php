<x-stat-card label="Total processes" tone="neutral" :value="$totalProcesses !== null ? number_format($totalProcesses) : '–'" />
<x-stat-card label="Max wait time (s)" tone="sky" :value="$maxWaitTimeSeconds !== null ? number_format($maxWaitTimeSeconds, 2) : '–'" />
<x-stat-card label="Max runtime" tone="violet" :value="$queueWithMaxRuntime !== null ? $queueWithMaxRuntime : '–'" />
<x-stat-card label="Max throughput" tone="emerald" :value="$queueWithMaxThroughput !== null ? $queueWithMaxThroughput : '–'" />
