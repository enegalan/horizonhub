<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\HorizonFailedJob;
use App\Models\HorizonJob;
use App\Models\Service;
use App\Models\HorizonSupervisorState;
use App\Services\HorizonApiProxyService;
use App\Services\HorizonMetricsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller {
    private const HOURS_24 = 24;
    private const DAYS_7 = 7;
    private const TOP_N_QUEUES = 12;
    private const TOP_N_SERVICES = 10;

    /**
     * Show the metrics dashboard.
     *
     * @return View
     */
    public function index(): View {
        $services = Service::orderBy('name')->get(['id', 'name']);

        return \view('horizon.metrics.index', [
            'jobsPastMinute' => null,
            'jobsPastHour' => null,
            'failedPastSevenDays' => null,
            'processedPast24Hours' => null,
            'failuresTable' => null,
            'failureRate24h' => null,
            'header' => 'Horizon Hub – Metrics',
            'services' => $services,
        ]);
    }

    /**
     * Get the summary data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    private HorizonApiProxyService $horizonApi;
    private HorizonMetricsService $metrics;

    public function __construct(HorizonApiProxyService $horizonApi, HorizonMetricsService $metrics) {
        $this->horizonApi = $horizonApi;
        $this->metrics = $metrics;
    }

    public function dataSummary(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        $service = $service_id !== null ? Service::find($service_id) : null;

        return $this->jsonOrFail(function () use ($service): array {
            return [
                'jobsPastMinute' => $this->metrics->getJobsPastMinute($service),
                'jobsPastHour' => $this->metrics->getJobsPastHour($service),
                'failedPastSevenDays' => $this->metrics->getFailedPastSevenDays($service),
                'processedPast24Hours' => $this->metrics->getProcessedPast24Hours($service),
                'failureRate24h' => $this->getFailureRate24h($service?->id),
            ];
        });
    }

    /**
     * Get the processed vs failed data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataProcessedVsFailed(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            $processedVsFailed = $this->getProcessedVsFailedOverTime($service_id);
            $failureRateOverTime = $this->getFailureRateOverTime($processedVsFailed);
            return [
                'processedVsFailed' => $processedVsFailed,
                'failureRateOverTime' => $failureRateOverTime,
            ];
        });
    }

    /**
     * Get the average runtime data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataAvgRuntime(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return $this->getAvgRuntimeOverTime($service_id);
        });
    }

    /**
     * Get the processed vs failed data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataByQueue(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return $this->getProcessedFailedByQueue($service_id);
        });
    }

    /**
     * Get the processed vs failed data by queue for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataByService(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return $this->getProcessedFailedByService($service_id);
        });
    }

    /**
     * Get the failures table data for the metrics dashboard.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dataFailuresTable(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);
        return $this->jsonOrFail(function () use ($service_id): array {
            return [
                'failuresTable' => $this->getFailuresByServiceQueue($service_id),
            ];
        });
    }

    /**
     * Get the supervisors data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataSupervisors(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);

        return $this->jsonOrFail(function () use ($service_id): array {
            return [
                'supervisors' => $this->getSupervisorsData($service_id),
            ];
        });
    }

    /**
     * Get the current workload data for the metrics dashboard.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function dataWorkload(Request $request): JsonResponse {
        $service_id = $this->resolveServiceId($request);

        return $this->jsonOrFail(function () use ($service_id): array {
            return [
                'workload' => $this->getWorkloadData($service_id),
            ];
        });
    }

    /**
     * Return a JSON response or fail with an error.
     *
     * @param callable(): array $fn
     * @return JsonResponse
     */
    private function jsonOrFail(callable $fn): JsonResponse {
        try {
            return \response()->json($fn());
        } catch (\Throwable $e) {
            \Log::error('MetricsController failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return \response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve optional service_id from request. Returns null for "all services".
     *
     * @param Request $request
     * @return int|null
     */
    private function resolveServiceId(Request $request): ?int {
        $raw = $request->query('service_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = \filter_var($raw, \FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            return null;
        }
        return Service::where('id', $id)->exists() ? $id : null;
    }

    /**
     * Normalize the queue name.
     *
     * @param string|null $queue
     * @return string|null
     */
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

    /**
     * Get the failures by service and queue.
     *
     * @param int|null $service_id
     * @return array<int, array{service: string, queue: string, cnt: int}>
     */
    private function getFailuresByServiceQueue(?int $service_id = null): array {
        $since = \now()->subDays(7);

        $rows = HorizonFailedJob::query()
            ->where('failed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
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
     * Get supervisors aggregated across services (optionally filtered by service).
     *
     * @param int|null $service_id
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     name: string,
     *     last_seen_iso: string|null,
     *     last_seen_human: string|null,
     *     status: string
     * }>
     */
    private function getSupervisorsData(?int $service_id = null): array {
        $deadThreshold = \now()->subMinutes((int) \config('horizonhub.dead_service_minutes'));

        $query = HorizonSupervisorState::query()
            ->where('last_seen_at', '>=', $deadThreshold)
            ->with('service:id,name');

        if ($service_id !== null) {
            $query->where('service_id', $service_id);
        }

        $rows = $query
            ->orderBy('service_id')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $service = $row->service;
            if (! $service) {
                continue;
            }

            $lastSeen = $row->last_seen_at;
            $lastSeenIso = $lastSeen ? $lastSeen->toIso8601String() : null;
            $lastSeenHuman = $lastSeen ? $lastSeen->diffForHumans() : null;

            $minutesAgo = $lastSeen ? (int) $lastSeen->diffInMinutes(\now(), true) : 0;
            $staleMinutes = (int) \config('horizonhub.stale_minutes');
            $status = $minutesAgo > $staleMinutes ? 'stale' : 'online';

            $result[] = [
                'service_id' => (int) $service->id,
                'service' => (string) $service->name,
                'name' => (string) $row->name,
                'last_seen_iso' => $lastSeenIso,
                'last_seen_human' => $lastSeenHuman,
                'status' => $status,
            ];
        }

        return $result;
    }

    /**
     * Get current workload aggregated across services (optionally filtered by service).
     *
     * @param int|null $service_id
     * @return array<int, array{
     *     service_id: int,
     *     service: string,
     *     queue: string,
     *     jobs: int,
     *     processes: int|null,
     *     wait: float|null
     * }>
     */
    private function getWorkloadData(?int $service_id = null): array {
        $servicesQuery = Service::query()->whereNotNull('base_url');

        if ($service_id !== null) {
            $servicesQuery->where('id', $service_id);
        }

        $services = $servicesQuery->orderBy('name')->get();
        if ($services->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($services as $service) {
            if (! $service instanceof Service) {
                continue;
            }

            $rows = $this->metrics->getWorkloadForService($service);
            if ($rows === []) {
                continue;
            }

            foreach ($rows as $row) {
                $result[] = [
                    'service_id' => (int) $service->id,
                    'service' => (string) $service->name,
                    'queue' => $row['queue'],
                    'jobs' => $row['jobs'],
                    'processes' => $row['processes'],
                    'wait' => $row['wait'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get the failure rate for the past 24 hours.
     *
     * @param int|null $service_id
     * @return array{rate: float, processed: int, failed: int}
     */
    private function getFailureRate24h(?int $service_id = null): array {
        $since = \now()->subDay();
        $processed = HorizonJob::where('created_at', '>=', $since)
            ->where('status', 'processed')
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->count();
        $failed = HorizonFailedJob::where('failed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
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
     * Get the processed vs failed data over time.
     *
     * @param int|null $service_id
     * @return array{xAxis: list<string>, processed: list<int>, failed: list<int>}
     */
    private function getProcessedVsFailedOverTime(?int $service_id = null): array {
        $since = \now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';
        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['processed' => 0, 'failed' => 0];
        }
        $processed = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->selectRaw('DATE_FORMAT(processed_at, "%Y-%m-%d %H:00") as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();
        foreach ($processed as $row) {
            $bucket = $row->bucket;
            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['processed'] = (int) $row->cnt;
            }
        }
        $failed = HorizonFailedJob::where('failed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->selectRaw('DATE_FORMAT(failed_at, "%Y-%m-%d %H:00") as bucket, COUNT(*) as cnt')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();
        foreach ($failed as $row) {
            $bucket = $row->bucket;
            if (isset($buckets[$bucket])) {
                $buckets[$bucket]['failed'] = (int) $row->cnt;
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
     * Get the failure rate over time.
     *
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
     * Get the average runtime over time.
     *
     * @param int|null $service_id
     * @return array{xAxis: list<string>, avgSeconds: list<float|null>}
     */
    private function getAvgRuntimeOverTime(?int $service_id = null): array {
        $since = \now()->subHours(self::HOURS_24);
        $bucketFormat = 'Y-m-d H:00';

        $buckets = [];
        for ($i = 0; $i < self::HOURS_24; $i++) {
            $key = \now()->subHours(self::HOURS_24 - 1 - $i)->format($bucketFormat);
            $buckets[$key] = ['avg_seconds' => null];
        }

        $driver = DB::connection()->getDriverName();
        $runtimeExpr = $driver === 'mysql'
            ? 'AVG(COALESCE(runtime_seconds, TIMESTAMPDIFF(SECOND, queued_at, processed_at)))'
            : 'AVG(COALESCE(runtime_seconds, (julianday(processed_at) - julianday(queued_at)) * 86400))';
        $bucketExpr = $driver === 'mysql'
            ? 'DATE_FORMAT(processed_at, "%Y-%m-%d %H:00")'
            : "strftime('%Y-%m-%d %H:00', processed_at)";

        $rows = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->whereNotNull('processed_at')
            ->whereNotNull('queued_at')
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->selectRaw("{$bucketExpr} as bucket, {$runtimeExpr} as avg_seconds, COUNT(*) as cnt")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        foreach ($rows as $row) {
            $bucket = $row->bucket;

            if (! isset($buckets[$bucket])) {
                continue;
            }

            $buckets[$bucket]['avg_seconds'] = $row->avg_seconds !== null ? (float) $row->avg_seconds : null;
        }

        $xAxis = [];
        $series = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = Carbon::parse($k)->format('H:i');
            $series[] = $v['avg_seconds'] !== null ? \round($v['avg_seconds'], 2) : null;
        }

        return ['xAxis' => $xAxis, 'avgSeconds' => $series];
    }

    /**
     * Get the processed vs failed data by queue.
     *
     * @param int|null $service_id
     * @return array{queues: list<string>, processed: list<int>, failed: list<int>}
     */
    private function getProcessedFailedByQueue(?int $service_id = null): array {
        $since = \now()->subDays(self::DAYS_7);
        $processedByQueue = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->selectRaw('queue, COUNT(*) as cnt')
            ->groupBy('queue')
            ->pluck('cnt', 'queue')
            ->all();
        $failedByQueue = HorizonFailedJob::where('failed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
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
     * Get the processed vs failed data by service.
     *
     * @param int|null $service_id
     * @return array{services: list<string>, processed: list<int>, failed: list[int]}
     */
    private function getProcessedFailedByService(?int $service_id = null): array {
        $since = \now()->subDays(self::DAYS_7);
        $processedByService = HorizonJob::where('status', 'processed')
            ->where('processed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
            ->selectRaw('service_id, COUNT(*) as cnt')
            ->groupBy('service_id')
            ->pluck('cnt', 'service_id')
            ->all();
        $failedByService = HorizonFailedJob::where('failed_at', '>=', $since)
            ->when($service_id !== null, fn ($q) => $q->where('service_id', $service_id))
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
