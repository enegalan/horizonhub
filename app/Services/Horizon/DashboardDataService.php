<?php

namespace App\Services\Horizon;

use App\Models\AlertLog;
use App\Models\Service;

class DashboardDataService
{
    /**
     * The Horizon metrics service.
     */
    private HorizonMetricsService $metrics;

    /**
     * The service stats attachment service.
     */
    private ServiceStatsAttachmentService $serviceStats;

    /**
     * The constructor.
     */
    public function __construct(HorizonMetricsService $metrics, ServiceStatsAttachmentService $serviceStats)
    {
        $this->metrics = $metrics;
        $this->serviceStats = $serviceStats;
    }

    /**
     * Build the dashboard data.
     *
     * @param HorizonApiProxyService $horizonApi The horizon API proxy service.
     * @param list<int> $serviceIds Empty means all services.
     *
     * @return array<string, mixed>
     */
    public function build(HorizonApiProxyService $horizonApi, array $serviceIds = []): array
    {
        $metrics = $this->metrics->buildMetricsDashboardData($serviceIds);

        $servicesQuery = Service::query()->orderBy('name');

        if (! empty($serviceIds)) {
            $servicesQuery->whereIn('id', $serviceIds);
        }

        $services = $servicesQuery->get();
        $this->serviceStats->attachHorizonStats($services, $horizonApi);

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

        $recentAlertLogsQuery = AlertLog::query()
            ->with(['alert', 'service'])
            ->orderByDesc('sent_at')
            ->limit(5);

        if (! empty($serviceIds)) {
            $recentAlertLogsQuery->whereIn('service_id', $serviceIds);
        }

        $recentAlertLogs = $recentAlertLogsQuery->get();

        $workloadRows = \is_array($metrics['workloadRows']) ? $metrics['workloadRows'] : [];

        return \array_merge($metrics, [
            'services' => $services,
            'servicesOnlineCount' => $onlineCount,
            'servicesTotal' => $enabledServices->count(),
            'servicesHealthDotClass' => $servicesHealthDotClass,
            'recentAlertLogs' => $recentAlertLogs,
            'workloadRows' => $workloadRows,
        ]);
    }
}
