<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Alert;

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
        $payload = $this->alertIndexStreamData->build();

        return $this->buildStreams([
            ['update', 'turbo-horizon-alert-stats', \view('horizon.alerts.partials.index.stats', ['alertStats' => $payload['alertStats']])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-alerts-list', \view('horizon.alerts.partials.index.tbody', ['alerts' => $payload['alerts'], 'serviceLabelsByAlertId' => $payload['serviceLabelsByAlertId']])->render(), 'morph'],
        ]);
    }
}
