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
        $d = $this->private__buildDashboardData();

        return $this->buildStreams([
            ['update', 'dashboard-value-jobs-minute', e($d['jobsPastMinute'] ?? '—'), null],
            ['update', 'dashboard-value-jobs-hour', e($d['jobsPastHour'] ?? '—'), null],
            ['update', 'dashboard-value-failed-seven', e($d['failedPastSevenDays'] ?? '—'), null],
            ['update', 'dashboard-services-kpi-inner', \view('horizon.dashboard.partials.index.kpi-services-online', [
                'servicesHealthDotClass' => $d['servicesHealthDotClass'] ?? 'bg-slate-400',
                'servicesOnlineCount' => $d['servicesOnlineCount'] ?? 0,
                'servicesTotal' => $d['servicesTotal'] ?? 0,
            ])->render(), 'morph'],
            ['update', 'dashboard-service-health-grid', \view('horizon.dashboard.partials.index.service-health-grid', ['services' => $d['services'] ?? collect()])->render(), 'morph'],
            ['update', 'dashboard-recent-alerts-body', \view('horizon.dashboard.partials.index.recent-alerts-tbody', ['recentAlertLogs' => $d['recentAlertLogs'] ?? collect()])->render(), 'morph'],
            ['update', 'dashboard-workload-summary-body', \view('horizon.dashboard.partials.index.workload-summary-tbody', ['workloadRows' => $d['workloadRows'] ?? []])->render(), 'morph'],
        ]);
    }

    /**
     * Build the dashboard data.
     *
     * @return array<string, mixed>
     */
    protected function private__buildDashboardData(): array
    {
        $metrics = $this->metrics->buildMetricsDashboardData([]);

        $services = Service::query()->orderBy('name')->get();
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

        $recentAlertLogs = AlertLog::query()
            ->with(['alert', 'service'])
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get();

        return \array_merge($metrics, [
            'services' => $services,
            'servicesOnlineCount' => $onlineCount,
            'servicesTotal' => $enabledServices->count(),
            'servicesHealthDotClass' => $servicesHealthDotClass,
            'recentAlertLogs' => $recentAlertLogs,
            'workloadRows' => $metrics['workloadRows'],
        ]);
    }
}
