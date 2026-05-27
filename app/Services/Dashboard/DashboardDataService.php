<?php

namespace App\Services\Dashboard;

use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Horizon\HorizonClientService;
use App\Services\Metrics\MetricsDataService;
use App\Services\Services\ServiceStatsAttachmentService;

final class DashboardDataService
{
    /**
     * The metrics data service.
     */
    private MetricsDataService $metrics;

    /**
     * The service stats attachment service.
     */
    private ServiceStatsAttachmentService $serviceStats;

    /**
     * The constructor.
     *
     * @param MetricsDataService $metrics The metrics data service.
     * @param ServiceStatsAttachmentService $serviceStats The service stats attachment service.
     */
    public function __construct(MetricsDataService $metrics, ServiceStatsAttachmentService $serviceStats)
    {
        $this->metrics = $metrics;
        $this->serviceStats = $serviceStats;
    }

    /**
     * Build the dashboard data.
     *
     * @param HorizonClientService $horizonApi The horizon API client.
     * @param list<int> $serviceIds Empty means all services.
     *
     * @return array<string, mixed>
     */
    public function build(HorizonClientService $horizonApi, array $serviceIds = []): array
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

        return \array_merge($metrics, [
            'services' => $services,
            'servicesOnlineCount' => $onlineCount,
            'servicesTotal' => $enabledServices->count(),
            'servicesHealthDotClass' => $servicesHealthDotClass,
            'recentAlertLogs' => $recentAlertLogsQuery->get(),
            'workloadRows' => $metrics['workloadRows'],
        ]);
    }
}
