<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Services\HorizonSyncService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class MetricsController extends Controller {
    private const HOURS_24 = 24;
    private const DAYS_7 = 7;
    private const TOP_N_QUEUES = 12;
    private const TOP_N_SERVICES = 10;

    /**
     * Show the metrics dashboard.
     *
     * @param HorizonSyncService $sync
     * @return View
     */
    public function index(HorizonSyncService $sync): View {
        $sync->syncRecentJobs(null);

        $jobsPastMinute = $this->getJobsPastMinute();
        $jobsPastHour = $this->getJobsPastHour();
        $failedPastSevenDays = $this->getFailedPastSevenDays();
        $processedPast24Hours = $this->getProcessedPast24Hours();
        $failuresTable = $this->getFailuresByServiceQueue();
        $failureRate24h = $this->getFailureRate24h();
        $processedVsFailed = $this->getProcessedVsFailedOverTime();
        $failureRateOverTime = $this->getFailureRateOverTime($processedVsFailed);
        $avgRuntimeOverTime = $this->getAvgRuntimeOverTime();
        $byQueue = $this->getProcessedFailedByQueue();
        $byService = $this->getProcessedFailedByService();

        $metricsChartData = [
            'processedVsFailed' => $processedVsFailed,
            'failureRateOverTime' => $failureRateOverTime,
            'avgRuntimeOverTime' => $avgRuntimeOverTime,
            'byQueue' => $byQueue,
            'byService' => $byService,
        ];

        return \view('horizon.metrics.index', [
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'processedPast24Hours' => $processedPast24Hours,
            'failuresTable' => $failuresTable,
            'failureRate24h' => $failureRate24h,
            'metricsChartData' => $metricsChartData,
            'header' => 'Horizon Hub – Metrics',
        ]);
    }

    private function normalizeQueueName(?string $queue): ?string {
        if ($queue === null || $queue === '') {
            return $queue;
        }

        if (\str_starts_with($queue, 'redis.')) {
            $suffix = \substr($queue, \strlen('redis.'));

            return $suffix !== '' ? $suffix : $queue;
        }

        return $queue;
    }

    private function getJobsPastMinute(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subMinute())
            ->count();
    }

    private function getJobsPastHour(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subHour())
            ->count();
    }

    private function getFailedPastSevenDays(): int {
        return HorizonFailedJob::where('failed_at', '>=', \now()->subDays(7))->count();
    }

    private function getProcessedPast24Hours(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subDay())
            ->count();
    }

    /**
     * @return array<int, array{service: string, queue: string, cnt: int}>
     */
    private function getFailuresByServiceQueue(): array {
        $since = \now()->subDays(7);

        $rows = HorizonFailedJob::query()
            ->where('failed_at', '>=', $since)
            ->selectRaw('service_id, queue, COUNT(*) as cnt')
            ->groupBy('service_id', 'queue')
            ->orderByDesc('cnt')
            ->limit(200)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $serviceIds = $rows
            ->pluck('service_id')
            ->filter(static fn ($id) => $id !== null)
            ->unique()
            ->all();

        $serviceNames = $serviceIds === []
            ? []
            : Service::whereIn('id', $serviceIds)->pluck('name', 'id')->all();

        $agg = [];
        foreach ($rows as $row) {
            $serviceId = $row->service_id;
            $serviceName = $serviceId !== null
                ? ($serviceNames[$serviceId] ?? (string) $serviceId)
                : 'Unknown';

            $queue = $this->normalizeQueueName($row->queue);
            $key = "$serviceName|$queue";

            if (! isset($agg[$key])) {
                $agg[$key] = [
                    'service' => $serviceName,
                    'queue' => $queue,
                    'cnt' => 0,
                ];
            }

            $agg[$key]['cnt'] += (int) $row->cnt;
        }

        \usort($agg, static fn (array $a, array $b): int => $b['cnt'] <=> $a['cnt']);

        return \array_slice($agg, 0, 15);
    }

    /**
     * @return array{rate: float, processed: int, failed: int}
     */
    private function getFailureRate24h(): array {
        $since = \now()->subDay();
        $processed = HorizonJob::where('created_at', '>=', $since)
            ->where('status', 'processed')
            ->count();
        $failed = HorizonFailedJob::where('failed_at', '>=', $since)
            ->count();
        $total = $processed + $failed;
        $rate = $total > 0 ? \round(100 * $failed / $total, 1) : 0;

        return [
            'rate' => $rate,
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{xAxis: list<string>, processed: list<int>, failed: list<int>}
     */
    private function getProcessedVsFailedOverTime(): array {
        $since = \now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';
        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['processed' => 0, 'failed' => 0];
        }
        $processed = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->get(['processed_at']);
        foreach ($processed as $j) {
            $key = $j->processed_at->format($bucketFormat);
            if (isset($buckets[$key])) {
                $buckets[$key]['processed']++;
            }
        }
        $failed = HorizonFailedJob::where('failed_at', '>=', $since)->get(['failed_at']);
        foreach ($failed as $j) {
            $key = $j->failed_at->format($bucketFormat);
            if (isset($buckets[$key])) {
                $buckets[$key]['failed']++;
            }
        }
        $xAxis = [];
        $processedSeries = [];
        $failedSeries = [];
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $processedSeries[] = $v['processed'];
            $failedSeries[] = $v['failed'];
        }

        return [
            'xAxis' => $xAxis,
            'processed' => $processedSeries,
            'failed' => $failedSeries,
        ];
    }

    /**
     * @param array{xAxis: list<string>, processed: list<int>, failed: list<int>} $data
     * @return array{xAxis: list<string>, rate: list<float>}
     */
    private function getFailureRateOverTime(array $data): array {
        $rates = [];
        foreach (\array_keys($data['processed']) as $i) {
            $p = $data['processed'][$i];
            $f = $data['failed'][$i];
            $total = $p + $f;
            $rates[] = $total > 0 ? \round(100 * $f / $total, 1) : 0.0;
        }

        return [
            'xAxis' => $data['xAxis'],
            'rate' => $rates,
        ];
    }

    /**
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    private function getAvgRuntimeOverTime(): array {
        $since = \now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';

        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['total' => 0.0, 'count' => 0];
        }

        $jobs = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->get();

        foreach ($jobs as $job) {
            if ($job->processed_at === null) {
                continue;
            }

            $bucket = $job->processed_at->copy()->setMinute(0)->setSecond(0)->format($bucketFormat);

            if (! isset($buckets[$bucket])) {
                continue;
            }

            $runtime = $job->getRuntimeSeconds();

            if ($runtime === null) {
                continue;
            }

            $buckets[$bucket]['total'] += $runtime;
            $buckets[$bucket]['count']++;
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $series[] = $v['count'] > 0 ? \round($v['total'] / $v['count'], 2) : null;
        }

        return ['xAxis' => $xAxis, 'avgSeconds' => $series];
    }

    /**
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    private function getProcessedFailedByQueue(): array {
        $since = \now()->subDays(self::DAYS_7);
        $processedByQueue = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->selectRaw('queue, COUNT(*) as cnt')
            ->groupBy('queue')
            ->pluck('cnt', 'queue')
            ->all();
        $failedByQueue = HorizonFailedJob::where('failed_at', '>=', $since)
            ->selectRaw('queue, COUNT(*) as cnt')
            ->groupBy('queue')
            ->pluck('cnt', 'queue')
            ->all();
        $normalizedProcessed = [];
        foreach ($processedByQueue as $queue => $cnt) {
            $normalized = $this->normalizeQueueName($queue);
            if (! isset($normalizedProcessed[$normalized])) {
                $normalizedProcessed[$normalized] = 0;
            }
            $normalizedProcessed[$normalized] += $cnt;
        }
        $normalizedFailed = [];
        foreach ($failedByQueue as $queue => $cnt) {
            $normalized = $this->normalizeQueueName($queue);
            if (! isset($normalizedFailed[$normalized])) {
                $normalizedFailed[$normalized] = 0;
            }
            $normalizedFailed[$normalized] += $cnt;
        }
        $allQueues = \array_unique(\array_merge(\array_keys($normalizedProcessed), \array_keys($normalizedFailed)));
        $agg = [];
        foreach ($allQueues as $q) {
            $agg[$q] = [
                'processed' => $normalizedProcessed[$q] ?? 0,
                'failed' => $normalizedFailed[$q] ?? 0,
            ];
        }
        \uasort($agg, fn ($a, $b) => ($b['processed'] + $b['failed']) <=> ($a['processed'] + $a['failed']));
        $agg = \array_slice($agg, 0, self::TOP_N_QUEUES, true);
        $queues = \array_keys($agg);
        $processed = [];
        $failed = [];
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }

        return ['queues' => $queues, 'processed' => $processed, 'failed' => $failed];
    }

    /**
     * @return array{services: list<string>, processed: list<int>, failed: list[int]}
     */
    private function getProcessedFailedByService(): array {
        $since = \now()->subDays(self::DAYS_7);
        $processedByService = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->selectRaw('service_id, COUNT(*) as cnt')
            ->groupBy('service_id')
            ->pluck('cnt', 'service_id')
            ->all();
        $failedByService = HorizonFailedJob::where('failed_at', '>=', $since)
            ->selectRaw('service_id, COUNT(*) as cnt')
            ->groupBy('service_id')
            ->pluck('cnt', 'service_id')
            ->all();
        $allIds = \array_unique(\array_merge(\array_keys($processedByService), \array_keys($failedByService)));
        $names = Service::whereIn('id', $allIds)->pluck('name', 'id')->all();
        $agg = [];
        foreach ($allIds as $id) {
            $name = $names[$id] ?? (string) $id;
            $p = $processedByService[$id] ?? 0;
            $f = $failedByService[$id] ?? 0;
            $agg[$name] = ['processed' => $p, 'failed' => $f, 'total' => $p + $f];
        }
        \uasort($agg, fn ($a, $b) => $b['total'] <=> $a['total']);
        $agg = \array_slice($agg, 0, self::TOP_N_SERVICES, true);
        $services = \array_keys($agg);
        $processed = [];
        $failed = [];
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }

        return ['services' => $services, 'processed' => $processed, 'failed' => $failed];
    }
}

