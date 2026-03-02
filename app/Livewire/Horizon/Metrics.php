<?php

namespace App\Livewire\Horizon;

use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Metrics extends Component {
    public int $rollingMinutes = 60;

    private const HOURS_24 = 24;

    private const DAYS_7 = 7;

    private const TOP_N_QUEUES = 12;

    private const TOP_N_SERVICES = 10;

    public function getListeners(): array {
        return [
            'echo:horizon-hub.dashboard,HorizonEvent' => 'refreshMetrics',
        ];
    }

    public function refreshMetrics(): void {
        // Re-render will fetch fresh data
    }

    public function getJobsPastMinute(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', now()->subMinute())
            ->count();
    }

    public function getJobsPastHour(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', now()->subHour())
            ->count();
    }

    public function getFailedPastSevenDays(): int {
        return HorizonFailedJob::where('failed_at', '>=', now()->subDays(7))->count();
    }

    public function getProcessedPast24Hours(): int {
        return HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', now()->subDay())
            ->count();
    }

    public function getFailuresByServiceQueue(): array {
        $since = now()->subDays(7);
        $rows = HorizonFailedJob::with('service')
            ->where('failed_at', '>=', $since)
            ->get();
        $agg = array();
        foreach ($rows as $r) {
            $s = $r->service?->name ?? (string) $r->service_id;
            $q = $r->queue ?? 'default';
            $key = $s . '|' . $q;
            if (! isset($agg[$key])) {
                $agg[$key] = array('service' => $s, 'queue' => $q, 'cnt' => 0);
            }
            $agg[$key]['cnt']++;
        }
        usort($agg, fn ($a, $b) => $b['cnt'] <=> $a['cnt']);
        return array_slice($agg, 0, 15);
    }

    public function getFailureRate24h(): array {
        $since = now()->subDay();
        $processed = HorizonJob::where('created_at', '>=', $since)->where('status', 'processed')->count();
        $failed = HorizonFailedJob::where('failed_at', '>=', $since)->count();
        $total = $processed + $failed;
        $rate = $total > 0 ? round(100 * $failed / $total, 1) : 0;
        return array(
            'rate' => $rate,
            'processed' => $processed,
            'failed' => $failed,
        );
    }

    /**
     * Processed and failed counts per hour for the last 24 hours.
     *
     * @return array{xAxis: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedVsFailedOverTime(): array {
        $since = now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';
        $buckets = array();
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = array('processed' => 0, 'failed' => 0);
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
        $xAxis = array();
        $processedSeries = array();
        $failedSeries = array();
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $processedSeries[] = $v['processed'];
            $failedSeries[] = $v['failed'];
        }
        return array(
            'xAxis' => $xAxis,
            'processed' => $processedSeries,
            'failed' => $failedSeries,
        );
    }

    /**
     * Failure rate (percent) per hour for the last 24 hours.
     *
     * @return array{xAxis: list<string>, rate: list<float>}
     */
    public function getFailureRateOverTime(): array {
        $data = $this->getProcessedVsFailedOverTime();
        $rates = array();
        foreach (array_keys($data['processed']) as $i) {
            $p = $data['processed'][$i];
            $f = $data['failed'][$i];
            $total = $p + $f;
            $rates[] = $total > 0 ? round(100 * $f / $total, 1) : 0.0;
        }
        return array(
            'xAxis' => $data['xAxis'],
            'rate' => $rates,
        );
    }

    /**
     * Average job runtime (seconds) per hour for the last 24 hours.
     *
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    public function getAvgRuntimeOverTime(): array {
        $since = now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';
        $driver = DB::getDriverName();
        $table = (new HorizonJob)->getTable();
        $buckets = array();
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = null;
        }
        if ($driver === 'mysql') {
            $rows = DB::select(
                "SELECT DATE_FORMAT(processed_at, ?) AS bucket, AVG(COALESCE(runtime_seconds, TIMESTAMPDIFF(SECOND, created_at, processed_at))) AS avg_seconds FROM {$table} WHERE processed_at >= ? AND status = 'processed' GROUP BY bucket ORDER BY bucket",
                array('%Y-%m-%d %H:00', $since->toDateTimeString())
            );
        } else {
            $rows = DB::select(
                "SELECT strftime('%Y-%m-%d %H:00', processed_at) AS bucket, AVG(COALESCE(runtime_seconds, (julianday(processed_at) - julianday(created_at)) * 86400)) AS avg_seconds FROM {$table} WHERE processed_at >= ? AND status = 'processed' GROUP BY bucket ORDER BY bucket",
                array($since->toDateTimeString())
            );
        }
        foreach ($rows as $r) {
            $bucket = $r->bucket;
            if (isset($buckets[$bucket])) {
                $buckets[$bucket] = round((float) $r->avg_seconds, 2);
            }
        }
        $xAxis = array();
        $series = array();
        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $series[] = $v;
        }
        return array('xAxis' => $xAxis, 'avgSeconds' => $series);
    }

    /**
     * Top queues by processed + failed count (last 7 days).
     *
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByQueue(): array {
        $since = now()->subDays(self::DAYS_7);
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
        $allQueues = array_unique(array_merge(array_keys($processedByQueue), array_keys($failedByQueue)));
        $agg = array();
        foreach ($allQueues as $q) {
            $agg[$q] = array(
                'processed' => $processedByQueue[$q] ?? 0,
                'failed' => $failedByQueue[$q] ?? 0,
            );
        }
        uasort($agg, fn ($a, $b) => ($b['processed'] + $b['failed']) <=> ($a['processed'] + $a['failed']));
        $agg = array_slice($agg, 0, self::TOP_N_QUEUES, true);
        $queues = array_keys($agg);
        $processed = array();
        $failed = array();
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }
        return array('queues' => $queues, 'processed' => $processed, 'failed' => $failed);
    }

    /**
     * Top services by processed + failed count (last 7 days).
     *
     * @return array{services: list<string>, processed: list<int>, failed: list<int>}
     */
    public function getProcessedFailedByService(): array {
        $since = now()->subDays(self::DAYS_7);
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
        $allIds = array_unique(array_merge(array_keys($processedByService), array_keys($failedByService)));
        $names = \App\Models\Service::whereIn('id', $allIds)->pluck('name', 'id')->all();
        $agg = array();
        foreach ($allIds as $id) {
            $name = $names[$id] ?? (string) $id;
            $p = $processedByService[$id] ?? 0;
            $f = $failedByService[$id] ?? 0;
            $agg[$name] = array('processed' => $p, 'failed' => $f, 'total' => $p + $f);
        }
        uasort($agg, fn ($a, $b) => $b['total'] <=> $a['total']);
        $agg = array_slice($agg, 0, self::TOP_N_SERVICES, true);
        $services = array_keys($agg);
        $processed = array();
        $failed = array();
        foreach ($agg as $v) {
            $processed[] = $v['processed'];
            $failed[] = $v['failed'];
        }
        return array('services' => $services, 'processed' => $processed, 'failed' => $failed);
    }

    public function render() {
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

        $metricsChartData = array(
            'processedVsFailed' => $processedVsFailed,
            'failureRateOverTime' => $failureRateOverTime,
            'avgRuntimeOverTime' => $avgRuntimeOverTime,
            'byQueue' => $byQueue,
            'byService' => $byService,
        );

        return view('livewire.horizon.metrics', [
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
