<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Metrics extends Component {
    /**
     * The number of rolling minutes to display.
     *
     * @var int
     */
    public int $rollingMinutes = 60;

    /**
     * The number of hours to display.
     *
     * @var int
     */
    private const HOURS_24 = 24;

    /**
     * The number of days to display.
     *
     * @var int
     */
    private const DAYS_7 = 7;

    /**
     * The number of top queues to display.
     *
     * @var int
     */
    private const TOP_N_QUEUES = 12;

    /**
     * The number of top services to display.
     *
     * @var int
     */
    private const TOP_N_SERVICES = 10;

    /**
     * Get the listeners for the metrics component.
     *
     * @return array<string, string>
     */
    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshMetrics',
        ];
    }

    /**
     * Refresh the metrics.
     * 
     * @internal This method is empty because the metrics are refreshed automatically when the Component is rendered.
     *
     * @return void
     */
    public function refreshMetrics(): void {
        // Re-render will fetch fresh data
    }

    /**
     * Get the number of processed jobs for the last minute.
     *
     * @return int
     */
    public function getJobsPastMinute(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subMinute())
            ->count();
    }

    /**
     * Get the number of processed jobs for the last hour.
     *
     * @return int
     */
    public function getJobsPastHour(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subHour())
            ->count();
    }

    /**
     * Get the number of failed jobs for the last 7 days.
     *
     * @return int
     */
    public function getFailedPastSevenDays(): int {
        return HorizonFailedJob::where('failed_at', '>=', \now()->subDays(7))->count();
    }

    /**
     * Get the number of processed jobs for the last 24 hours.
     *
     * @return int
     */
    public function getProcessedPast24Hours(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', \now()->subDay())
            ->count();
    }

    /**
     * Get the failure rate by service and queue for the last 7 days.
     *
     * @return array<int, array{service: string, queue: string, cnt: int}>
     */
    public function getFailuresByServiceQueue(): array {
        $since = \now()->subDays(7);
        $rows = HorizonFailedJob::with('service')
            ->where('failed_at', '>=', $since)
            ->get();
        $agg = [];
        foreach ($rows as $r) {
            $s = $r->service?->name ?? (string) $r->service_id;
            $q = $r->queue ?? 'default';
            $key = "$s|$q";
            if (! isset($agg[$key])) {
                $agg[$key] = ['service' => $s, 'queue' => $q, 'cnt' => 0];
            }
            $agg[$key]['cnt']++;
        }
        \usort($agg, fn ($a, $b) => $b['cnt'] <=> $a['cnt']);
        return \array_slice($agg, 0, 15);
    }

    /**
     * Failure rate (percent) for the last 24 hours.
     *
     * @return array{rate: float}
     */
    public function getFailureRate24h(): array {
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
     * Processed and failed counts per hour for the last 24 hours.
     *
     * @return array{xAxis: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedVsFailedOverTime(): array {
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
     * Failure rate (percent) per hour for the last 24 hours.
     *
     * @return array{xAxis: list<string>, rate: list<float>}
     */
    public function getFailureRateOverTime(): array {
        $data = $this->getProcessedVsFailedOverTime();
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
     * Average job runtime (seconds) per hour for the last 24 hours.
     *
     * This implementation is database-driver agnostic and relies on the
     * model's runtime calculation instead of raw SQL date functions.
     *
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    public function getAvgRuntimeOverTime(): array {
        $since = \now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';

        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['total' => 0.0, 'count' => 0];
        }

        $jobs = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->get(['queued_at', 'created_at', 'processed_at', 'failed_at', 'runtime_seconds']);

        foreach ($jobs as $job) {
            /** @var \App\Models\HorizonJob $job */
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
     * Top queues by processed + failed count (last 7 days).
     *
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(): array {
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
        $allQueues = \array_unique(\array_merge(\array_keys($processedByQueue), \array_keys($failedByQueue)));
        $agg = [];
        foreach ($allQueues as $q) {
            $agg[$q] = [
                'processed' => $processedByQueue[$q] ?? 0,
                'failed' => $failedByQueue[$q] ?? 0,
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
     * Top services by processed + failed count (last 7 days).
     *
     * @return array{services: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByService(): array {
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

    /**
     * Render the metrics component.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function render(): View {
        $jobsPastMinute = $this->getJobsPastMinute();
        $jobsPastHour = $this->getJobsPastHour();
        $failedPastSevenDays = $this->getFailedPastSevenDays();
        $processedPast24Hours = $this->getProcessedPast24Hours();
        $failuresTable = $this->getFailuresByServiceQueue();
        $failureRate24h = $this->getFailureRate24h();
        $processedVsFailed = $this->getProcessedVsFailedOverTime();
        $failureRateOverTime = $this->getFailureRateOverTime();
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

        return \view('livewire.horizon.metrics', [
            'jobsPastMinute' => $jobsPastMinute,
            'jobsPastHour' => $jobsPastHour,
            'failedPastSevenDays' => $failedPastSevenDays,
            'processedPast24Hours' => $processedPast24Hours,
            'failuresTable' => $failuresTable,
            'failureRate24h' => $failureRate24h,
            'processedVsFailed' => $processedVsFailed,
            'failureRateOverTime' => $failureRateOverTime,
            'avgRuntimeOverTime' => $avgRuntimeOverTime,
            'byQueue' => $byQueue,
            'byService' => $byService,
            'metricsChartData' => $metricsChartData,
        ])->layout('layouts.app', ['header' => 'Horizon Hub – Metrics']);
    }
}
