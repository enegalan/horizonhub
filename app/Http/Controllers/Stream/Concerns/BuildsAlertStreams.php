<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Alert;
use App\Models\Service;

trait BuildsAlertStreams
{
    private function private__buildAlertShowStreams(Alert $alert): string
    {
        $chartData = [
            'chart24h' => $this->alertChartData->buildChart($alert, 1),
            'chart7d' => $this->alertChartData->buildChart($alert, 7),
            'chart30d' => $this->alertChartData->buildChart($alert, 30),
        ];

        $statsHtml = \view('horizon.alerts.partials.show.stats', [
            'chartData' => $chartData,
        ])->render();

        $chartDataHtml = \view('components.horizon.alert-detail-chart-data', [
            'chartData' => $chartData,
        ])->render();

        return $this->buildStreams([
            ['update', 'alert-detail-stats', $statsHtml, 'morph'],
            ['replace', 'alert-detail-chart-data', $chartDataHtml, null],
        ]);
    }

    private function private__buildAlertsStreams(): string
    {
        $alerts = Alert::query()
            ->withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at')
            ->get();

        $serviceNamesById = Service::query()->pluck('name', 'id')->all();
        $labelsByAlertId = [];

        foreach ($alerts as $alert) {
            $labels = [];

            foreach ($alert->service_ids as $serviceId) {
                $name = $serviceNamesById[$serviceId] ?? null;

                if (! empty($name)) {
                    $labels[] = $name;
                }
            }

            $labelsByAlertId[$alert->id] = $labels;
        }

        $payload = [
            'alerts' => $alerts,
            'alertStats' => [
                'total' => $alerts->count(),
                'enabled' => $alerts->where('enabled')->count(),
                'disabled' => $alerts->where('enabled', false)->count(),
            ],
            'serviceLabelsByAlertId' => $labelsByAlertId,
        ];

        return $this->buildStreams([
            ['update', 'turbo-horizon-alert-stats', \view('horizon.alerts.partials.index.stats', ['alertStats' => $payload['alertStats']])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-alerts-list', \view('horizon.alerts.partials.index.tbody', ['alerts' => $payload['alerts'], 'serviceLabelsByAlertId' => $payload['serviceLabelsByAlertId']])->render(), 'morph'],
        ]);
    }
}
