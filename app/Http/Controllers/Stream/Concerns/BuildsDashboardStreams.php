<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\ServiceStatus;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;

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
        $services = Service::getServices($serviceFilterIds, false, true);

        foreach ($services as $service) {
            $service->withHorizonStats();
        }

        $enabledServices = $services->where('enabled', true);

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
            ['update', 'dashboard-services-kpi-inner', \view('horizon.dashboard.partials.index.kpi-services-online', [
                'servicesCount' => $enabledServices->count(),
                'offlineCount' => $enabledServices->where('status', ServiceStatus::Offline)->count(),
                'standByCount' => $enabledServices->where('status', ServiceStatus::StandBy)->count(),
                'onlineCount' => $enabledServices->where('status', ServiceStatus::Online)->count(),
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('horizon.dashboard.partials.index.service-health-grid', ['services' => $services])->render(), 'morph'],
            ['update', 'dashboard-recent-alerts-body', \view('horizon.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs])->render(), 'morph'],
            ['update', 'dashboard-workload-summary-body', \view('horizon.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $metrics['workloadRows']])->render(), 'morph'],
        ]);
    }
}
