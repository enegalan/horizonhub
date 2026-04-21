<?php

namespace App\Services\Horizon;

use App\Models\AlertLog;
use App\Models\Service;

class DashboardDataService
{
    /**
     * The metrics dashboard data service.
     */
    private MetricsDashboardDataService $metricsDashboard;

    /**
     * The service stats attachment service.
     */
    private ServiceStatsAttachmentService $serviceStats;

    /**
     * The constructor.
     */
    public function __construct(MetricsDashboardDataService $metricsDashboard, ServiceStatsAttachmentService $serviceStats)
    {
        $this->metricsDashboard = $metricsDashboard;
        $this->serviceStats = $serviceStats;
    }

    /**
     * Build the dashboard data.
     *
     * @param HorizonApiProxyService $horizonApi The horizon API proxy service.
     *
     * @return array<string, mixed>
     */
    public function build(HorizonApiProxyService $horizonApi): array
    {
        $metrics = $this->metricsDashboard->build([]);

        $services = Service::query()->orderBy('name')->get();
        $this->serviceStats->attachHorizonStats($services, $horizonApi);

        $onlineCount = 0;
        $anyOffline = false;
        $anyStandBy = false;

        foreach ($services as $service) {
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

        $servicesHealthDotClass = $services->isEmpty() ? 'bg-slate-400' :
            ($anyOffline ? 'bg-red-500' :
                ($anyStandBy ? 'bg-amber-500' : 'bg-emerald-500'));

        $recentAlertLogs = AlertLog::query()
            ->with(['alert', 'service'])
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get();

        $workloadRows = \is_array($metrics['workloadRows']) ? $metrics['workloadRows'] : [];

        return \array_merge($metrics, [
            'services' => $services,
            'servicesOnlineCount' => $onlineCount,
            'servicesTotal' => $services->count(),
            'servicesHealthDotClass' => $servicesHealthDotClass,
            'recentAlertLogs' => $recentAlertLogs,
            'workloadRows' => $workloadRows,
        ]);
    }
}
