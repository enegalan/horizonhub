<?php

namespace App\Http\Controllers\Stream\Concerns;

trait BuildsDashboardStreams
{
    private function buildDashboard(): string
    {
        $d = $this->dashboardData->build($this->horizonApi);

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
}
