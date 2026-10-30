<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\AlertLogStatus;
use App\Enums\ServiceStatus;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Horizon\HorizonClientApiService;
use App\Support\Horizon\ClientResponse;
use App\Support\Horizon\StatsReader;
use App\Support\Jobs\JobRuntime;
use App\Support\NumberFormatter;
use Illuminate\Support\Collection;

trait BuildsDashboardStreams
{
    /**
     * Build the dashboard streams.
     */
    protected function buildDashboard(): string
    {
        $metrics = $this->metrics->buildMetricsDashboardData([]);
        $overview = $this->queueOverview->build([], false);

        $completedJobs = 0;
        $failedJobs = 0;
        $jobsPerMinute = 0;
        $silencedJobs = 0;
        $batchesTotal = 0;
        $batchesInProgress = 0;
        $batchesWithFailures = 0;

        /** @var Service $service */
        foreach ($overview['services'] as $service) {
            $statsData = ClientResponse::data(HorizonClientApiService::getStats($service));
            $stats = StatsReader::summary($statsData);
            $completedJobs += (int) $stats['recentJobs'];
            $failedJobs += (int) $stats['failedJobs'];
            $jobsPerMinute += (int) $stats['jobsPastMinute'];

            $silencedData = ClientResponse::data(HorizonClientApiService::getSilencedJobs($service));

            if ($silencedData !== null) {
                $silencedJobs += NumberFormatter::nonNegativeInt($silencedData['total'] ?? null);
            }

            $batchesData = ClientResponse::data(HorizonClientApiService::getBatches($service));

            if ($batchesData !== null) {
                $batchStats = $this->private__batchStats($batchesData['batches'] ?? null);
                $batchesTotal += $batchStats['total'];
                $batchesInProgress += $batchStats['inProgress'];
                $batchesWithFailures += $batchStats['withFailures'];
            }
        }

        $failedSeries = $metrics['jobsVolumeLast24h']['failed'] ?? [];
        $failedSeries = \is_array($failedSeries) ? $failedSeries : [];
        $failedPast24h = 0;

        foreach ($failedSeries as $count) {
            $failedPast24h += (int) $count;
        }

        $failedPastHour = $failedSeries === []
            ? 0
            : (int) $failedSeries[\array_key_last($failedSeries)];

        $pendingActiveQueues = 0;

        foreach ($overview['rows'] as $row) {
            if ((int) ($row['jobs'] ?? 0) > 0) {
                $pendingActiveQueues++;
            }
        }

        $stats = [
            'pendingJobs' => (int) $overview['totals']['totalJobs'],
            'pendingActiveQueues' => $pendingActiveQueues,
            'pendingQueueCount' => (int) $overview['totals']['queueCount'],
            'failedJobs' => $failedJobs,
            'failedPastHour' => $failedPastHour,
            'failedPast24h' => $failedPast24h,
            'completedJobs' => $completedJobs,
            'jobsPerMinute' => $jobsPerMinute,
            'silencedJobs' => $silencedJobs,
            'batchesTotal' => $batchesTotal,
            'batchesInProgress' => $batchesInProgress,
            'batchesWithFailures' => $batchesWithFailures,
        ];

        $supervisorStats = $this->private__supervisorStats($overview['masterGroups']);

        $services = Service::getServices([], false, true);

        foreach ($services as $service) {
            $service->withHorizonStats();
        }

        $enabledServices = $services->where('enabled', true);
        $servicesCount = $enabledServices->count();

        $recentAlertLogs = AlertLog::with(['alert', 'service'])
            ->orderByDesc('sent_at')
            ->limit(config('horizonhub.recent_alert_logs'))
            ->get();

        $alertStats = $this->private__alertStats($recentAlertLogs);
        $workloadRows = $metrics['workloadRows'] ?? [];
        $workloadRows = \is_array($workloadRows) ? $workloadRows : [];
        $workloadStats = $this->private__workloadStats($workloadRows);
        $workloadListRows = $this->private__topWorkloadRows($workloadRows);

        return $this->buildStreams([
            ['update', 'dashboard-stats', \view('pages.dashboard.partials.index.stats', [
                'stats' => $stats,
            ])->render(), 'morph'],
            ['update', 'dashboard-supervisor-stats', \view('pages.dashboard.partials.index.supervisor-stats', [
                'supervisorStats' => $supervisorStats,
            ])->render(), 'morph'],
            ['update', 'dashboard-supervisors', \view('pages.partials.master-groups', [
                'emptyDescription' => 'No supervisors were reported by the connected Horizon services.',
                'emptyTitle' => 'No supervisors',
                'masterGroups' => $overview['masterGroups'],
                'showServiceLink' => true,
            ])->render(), 'morph'],
            ['update', 'dashboard-services-kpi-inner', \view('pages.dashboard.partials.index.kpi-services-online', [
                'servicesCount' => $servicesCount,
                'offlineCount' => $enabledServices->where('status', ServiceStatus::Offline)->count(),
                'standByCount' => $enabledServices->where('status', ServiceStatus::StandBy)->count(),
                'onlineCount' => $enabledServices->where('status', ServiceStatus::Online)->count(),
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('pages.dashboard.partials.index.service-health-grid', [
                'services' => $this->private__sortServicesForHealthGrid($services),
            ])->render(), 'morph'],
            ['update', 'dashboard-alert-stats', \view('pages.dashboard.partials.index.alert-stats', [
                'alertStats' => $alertStats,
            ])->render(), 'morph'],
            ['update', 'dashboard-alerts-list', \view('pages.dashboard.partials.index.recent-alerts-list', ['recentAlertLogs' => $recentAlertLogs])->render(), 'morph'],
            ['update', 'dashboard-workload-stats', \view('pages.dashboard.partials.index.workload-stats', [
                'workloadStats' => $workloadStats,
            ])->render(), 'morph'],
            ['update', 'dashboard-workload-list', \view('pages.dashboard.partials.index.workload-list', [
                'workloadRows' => $workloadListRows,
            ])->render(), 'morph'],
        ]);
    }

    /**
     * Aggregate alert strip metrics.
     *
     * @param Collection<int, AlertLog>|\Illuminate\Database\Eloquent\Collection<int, AlertLog> $recentAlertLogs
     *
     * @return array{totalAlerts: int, enabledAlerts: int, failedDeliveries: int}
     */
    private function private__alertStats($recentAlertLogs): array
    {
        $failedDeliveries = 0;

        foreach ($recentAlertLogs as $log) {
            if ($log->status === AlertLogStatus::Failed) {
                $failedDeliveries++;
            }
        }

        return [
            'totalAlerts' => Alert::query()->count(),
            'enabledAlerts' => Alert::query()->enabled()->count(),
            'failedDeliveries' => $failedDeliveries,
        ];
    }

    /**
     * Aggregate batch counts from a Horizon batches payload.
     *
     * @return array{total: int, inProgress: int, withFailures: int}
     */
    private function private__batchStats(mixed $batches): array
    {
        if (! \is_array($batches)) {
            return ['total' => 0, 'inProgress' => 0, 'withFailures' => 0];
        }

        $total = 0;
        $inProgress = 0;
        $withFailures = 0;

        foreach ($batches as $batch) {
            if (! \is_array($batch)) {
                continue;
            }

            $total++;
            $pendingJobs = NumberFormatter::nonNegativeInt($batch['pendingJobs'] ?? null);
            $failedJobs = NumberFormatter::nonNegativeInt($batch['failedJobs'] ?? null);
            $finishedAt = JobRuntime::parseJobTimestamp($batch['finishedAt'] ?? null);
            $cancelledAt = JobRuntime::parseJobTimestamp($batch['cancelledAt'] ?? null);

            if ($failedJobs > 0) {
                $withFailures++;
            }

            if ($cancelledAt === null && $finishedAt === null && $pendingJobs > 0) {
                $inProgress++;
            }
        }

        return [
            'total' => $total,
            'inProgress' => $inProgress,
            'withFailures' => $withFailures,
        ];
    }

    /**
     * Sort services for the dashboard health grid: offline first, then stand-by, online, disabled.
     *
     * @param Collection<int, Service>|\Illuminate\Database\Eloquent\Collection<int, Service> $services
     *
     * @return Collection<int, Service>
     */
    private function private__sortServicesForHealthGrid($services): Collection
    {
        return $services
            ->sortBy(static function (Service $service): int {
                if (! $service->enabled) {
                    return 3;
                }

                return match ($service->status) {
                    ServiceStatus::Online => 2,
                    ServiceStatus::StandBy => 1,
                    default => 0,
                };
            })
            ->values();
    }

    /**
     * Aggregate supervisor strip metrics from master groups.
     *
     * @param list<array<string, mixed>> $masterGroups
     *
     * @return array{totalInstances: int, totalSupervisors: int, pausedSupervisors: int}
     */
    private function private__supervisorStats(array $masterGroups): array
    {
        $totalSupervisors = 0;
        $pausedSupervisors = 0;

        foreach ($masterGroups as $group) {
            $supervisors = $group['supervisors'] ?? [];

            if (! \is_array($supervisors)) {
                continue;
            }

            foreach ($supervisors as $supervisor) {
                if (! \is_array($supervisor)) {
                    continue;
                }

                $totalSupervisors++;
                $status = \strtolower((string) ($supervisor['status'] ?? ''));

                if (\in_array($status, ['paused', 'inactive'], true)) {
                    $pausedSupervisors++;
                }
            }
        }

        return [
            'totalInstances' => \count($masterGroups),
            'totalSupervisors' => $totalSupervisors,
            'pausedSupervisors' => $pausedSupervisors,
        ];
    }

    /**
     * Highest-workload rows for the dashboard list (stats keep the full set).
     *
     * @param list<array<string, mixed>> $workloadRows
     *
     * @return list<array<string, mixed>>
     */
    private function private__topWorkloadRows(array $workloadRows): array
    {
        if ($workloadRows === []) {
            return [];
        }

        \usort($workloadRows, static function (array $left, array $right): int {
            return ((int) ($right['jobs'] ?? 0)) <=> ((int) ($left['jobs'] ?? 0));
        });

        return \array_values(\array_slice($workloadRows, 0, config('horizonhub.top_n_queues')));
    }

    /**
     * Aggregate workload strip metrics from workload rows.
     *
     * @param list<array<string, mixed>> $workloadRows
     *
     * @return array{totalJobs: int, activeQueues: int, queueCount: int}
     */
    private function private__workloadStats(array $workloadRows): array
    {
        $totalJobs = 0;
        $activeQueues = 0;

        foreach ($workloadRows as $row) {
            $jobs = (int) ($row['jobs'] ?? 0);
            $totalJobs += $jobs;

            if ($jobs > 0) {
                $activeQueues++;
            }
        }

        return [
            'totalJobs' => $totalJobs,
            'activeQueues' => $activeQueues,
            'queueCount' => \count($workloadRows),
        ];
    }
}
