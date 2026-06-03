<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\AlertLog;
use App\Models\Service;

trait BuildsDashboardStreams
{
    /**
     * Build the dashboard streams.
     */
    protected function buildDashboard(): string
    {
        $metrics = $this->metrics->buildMetricsDashboardData([]);

        $services = Service::orderBy('name')->get();
        $this->serviceStats->attachHorizonStats($services, $this->horizonApi);

        $onlineCount = 0;
        $anyOffline = false;
        $anyStandBy = false;

        $enabledServices = $services->where('enabled', true);

        foreach ($enabledServices as $service) {
            if ($service->status === 'online') {
                $onlineCount++;
            }

            if ($service->status === 'offline') {
                $anyOffline = true;
            }

            if ($service->status === 'stand_by') {
                $anyStandBy = true;
            }
        }

        $servicesHealthDotClass = $enabledServices->isEmpty() ? 'bg-slate-400' :
            ($anyOffline ? 'bg-orange-500' :
                ($anyStandBy ? 'bg-amber-500' : 'bg-emerald-500'));

        $recentAlertLogs = AlertLog::with(['alert', 'service'])
            ->orderByDesc('sent_at')
            ->limit(5) // TODO: make this configurable
            ->get();

        return $this->buildStreams([
            ['update', 'dashboard-value-jobs-minute', e($metrics['jobsPastMinute'] ?? '—'), null],
            ['update', 'dashboard-value-jobs-hour', e($metrics['jobsPastHour'] ?? '—'), null],
            ['update', 'dashboard-value-failed-seven', e($metrics['failedPastSevenDays'] ?? '—'), null],
            ['update', 'dashboard-services-kpi-inner', \view('horizon.dashboard.partials.index.kpi-services-online', [
                'servicesHealthDotClass' => $servicesHealthDotClass,
                'servicesOnlineCount' => $onlineCount,
                'servicesTotal' => $enabledServices->count(),
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('horizon.dashboard.partials.index.service-health-grid', ['services' => $services])->render(), 'morph'],
            ['update', 'dashboard-recent-alerts-body', \view('horizon.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $recentAlertLogs])->render(), 'morph'],
            ['update', 'dashboard-workload-summary-body', \view('horizon.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $metrics['workloadRows']])->render(), 'morph'],
        ]);
    }
}
