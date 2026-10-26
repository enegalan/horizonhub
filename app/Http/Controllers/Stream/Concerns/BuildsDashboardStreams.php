<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\ServiceStatus;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Services\Services\ServiceFilterService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\StatsReader;
use App\Support\NumberFormatter;
use App\Support\Queues\QueueNameNormalizer;

trait BuildsDashboardStreams
{
    /**
     * Build the dashboard streams.
     *
     * @param string $query The query.
     */
    protected function buildDashboard(string $query): string
    {
        $serviceFilterIds = ServiceFilterService::resolveServiceIdsFromQuery($query);
        $metrics = $this->metrics->buildMetricsDashboardData($serviceFilterIds);
        $overview = $this->queueOverview->build($serviceFilterIds, false);
        $processes = 0;
        $silencedJobs = 0;
        $silencedAvailable = true;
        $recentBatches = 0;
        $batchesAvailable = true;
        $batchesMayBeTruncated = false;
        $maxWaitSeconds = null;
        $maxWaitQueue = null;
        $queueWithMaxRuntime = null;
        $queueWithMaxThroughput = null;
        $batchLimit = config('horizonhub.batches_recent_limit');

        /** @var Service $service */
        foreach ($overview['services'] as $service) {
            $statsData = ClientResponse::data(HorizonClientApiService::getStats($service));
            $stats = StatsReader::summary($statsData);
            $processes += (int) $stats['processes'];

            $waitEntry = StatsReader::maxWaitEntry($statsData);

            if ($waitEntry !== null && ($maxWaitSeconds === null || $waitEntry['seconds'] > $maxWaitSeconds)) {
                $maxWaitSeconds = $waitEntry['seconds'];
                $maxWaitQueue = QueueNameNormalizer::normalize($waitEntry['queue']);
            }

            if ($queueWithMaxRuntime === null && $stats['queueWithMaxRuntime'] !== null) {
                $queueWithMaxRuntime = QueueNameNormalizer::normalize($stats['queueWithMaxRuntime']);
            }

            if ($queueWithMaxThroughput === null && $stats['queueWithMaxThroughput'] !== null) {
                $queueWithMaxThroughput = QueueNameNormalizer::normalize($stats['queueWithMaxThroughput']);
            }

            $silencedData = ClientResponse::data(HorizonClientApiService::getSilencedJobs($service));

            if ($silencedData === null) {
                $silencedAvailable = false;
            } else {
                $silencedJobs += NumberFormatter::nonNegativeInt($silencedData['total'] ?? null);
            }

            $batchesData = ClientResponse::data(HorizonClientApiService::getBatches($service));

            if ($batchesData === null) {
                $batchesAvailable = false;
            } else {
                $batchCount = NumberFormatter::count($batchesData['batches'] ?? null);
                $recentBatches += $batchCount;
                $batchesMayBeTruncated = $batchesMayBeTruncated || $batchCount >= $batchLimit;
            }
        }

        $summary = [
            'pendingJobs' => $overview['totals']['totalJobs'],
            'processes' => $processes,
            'silencedJobs' => $silencedAvailable ? $silencedJobs : null,
            'recentBatches' => $batchesAvailable ? $recentBatches : null,
            'batchesMayBeTruncated' => $batchesMayBeTruncated,
            'maxWaitSeconds' => $maxWaitSeconds,
            'maxWaitQueue' => $maxWaitQueue,
            'queueWithMaxRuntime' => $queueWithMaxRuntime,
            'queueWithMaxThroughput' => $queueWithMaxThroughput,
        ];

        $services = Service::getServices($serviceFilterIds, false, true);

        foreach ($services as $service) {
            $service->withHorizonStats();
        }

        $enabledServices = $services->where('enabled', true);
        $silencedValue = $summary['silencedJobs'] !== null
            ? e(\number_format($summary['silencedJobs']))
            : '—';
        $batchesValue = $summary['recentBatches'] !== null
            ? e(\number_format($summary['recentBatches']) . ($summary['batchesMayBeTruncated'] ? '+' : ''))
            : '—';

        $recentAlertLogsQuery = AlertLog::with(['alert', 'service'])
            ->orderByDesc('sent_at');

        if (! empty($serviceFilterIds)) {
            $recentAlertLogsQuery->whereIn('service_id', $serviceFilterIds);
        }

        $recentAlertLogs = $recentAlertLogsQuery
            ->limit(config('horizonhub.recent_alert_logs'))
            ->get();

        return $this->buildStreams([
            ['update', 'dashboard-value-jobs-minute', e($metrics['jobsPastMinute'] ?? '—'), null],
            ['update', 'dashboard-value-jobs-hour', e($metrics['jobsPastHour'] ?? '—'), null],
            ['update', 'dashboard-value-failed-seven', e($metrics['failedPastSevenDays'] ?? '—'), null],
            ['update', 'dashboard-value-pending', e(\number_format($summary['pendingJobs'])), null],
            ['update', 'dashboard-value-silenced', $silencedValue, null],
            ['update', 'dashboard-value-batches', $batchesValue, null],
            ['update', 'dashboard-value-processes', e(\number_format($summary['processes'])), null],
            ['update', 'dashboard-limits-summary', \view('pages.dashboard.partials.index.limits-summary', [
                'summary' => $summary,
            ])->render(), 'morph'],
            ['update', 'dashboard-supervisors', \view('pages.partials.master-groups', [
                'emptyDescription' => 'No supervisors were reported by the connected Horizon services.',
                'emptyTitle' => 'No supervisors',
                'masterGroups' => $overview['masterGroups'],
                'showServiceLink' => true,
            ])->render(), 'morph'],
            ['update', 'dashboard-services-kpi-inner', \view('pages.dashboard.partials.index.kpi-services-online', [
                'servicesCount' => $enabledServices->count(),
                'offlineCount' => $enabledServices->where('status', ServiceStatus::Offline)->count(),
                'standByCount' => $enabledServices->where('status', ServiceStatus::StandBy)->count(),
                'onlineCount' => $enabledServices->where('status', ServiceStatus::Online)->count(),
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('pages.dashboard.partials.index.service-health-grid', ['services' => $services])->render(), 'morph'],
            ['update', 'tbody-horizon-dashboard-alerts', \view('pages.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs])->render(), 'morph'],
            ['update', 'tbody-horizon-dashboard-workload', \view('pages.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $metrics['workloadRows']])->render(), 'morph'],
        ]);
    }
}
