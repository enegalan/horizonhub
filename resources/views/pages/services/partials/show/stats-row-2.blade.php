<x-stat-card label="Total processes" :value="$totalProcesses !== null ? number_format($totalProcesses) : '–'" />
<x-stat-card label="Max wait time" :value="$maxWaitTimeSeconds !== null ? (\App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $maxWaitTimeSeconds) ?? '–') : '–'" />
<x-stat-card label="Max runtime" :value="$queueWithMaxRuntime !== null ? $queueWithMaxRuntime : '–'" />
<x-stat-card label="Max throughput" :value="$queueWithMaxThroughput !== null ? $queueWithMaxThroughput : '–'" />
