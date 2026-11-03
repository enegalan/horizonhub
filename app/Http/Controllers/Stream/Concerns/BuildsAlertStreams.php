<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Alert;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use App\Support\Alerts\AlertChartBuilder;

trait BuildsAlertStreams
{
    /**
     * Build the alerts index streams.
     *
     * @param string $query The query.
     */
    protected function buildAlerts(string $query = ''): string
    {
        $search = ServiceFilterService::searchFromQuery($query);

        $alertsQuery = Alert::withCount('alertLogs')
            ->withMax('alertLogs', 'sent_at')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $alertsQuery->where('name', 'like', "%$search%");
        }

        $alerts = $alertsQuery->get();

        $serviceNamesById = Service::pluck('name', 'id')->all();
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

        return $this->buildStreams([
            ['update', 'horizon-alert-stats', \view('pages.alerts.partials.index.stats', ['alertStats' => ['total' => $alerts->count(), 'enabled' => $alerts->where('enabled')->count(), 'disabled' => $alerts->where('enabled', false)->count()]])->render(), 'morph'],
            ['update', 'tbody-horizon-alerts-list', \view('pages.alerts.partials.index.tbody', ['alerts' => $alerts, 'serviceLabelsByAlertId' => $labelsByAlertId])->render(), 'morph'],
        ]);
    }

    /**
     * Build the alert show streams.
     *
     * @param Alert $alert The alert.
     */
    protected function buildAlertShow(Alert $alert): string
    {
        $chartData = [
            'chart24h' => AlertChartBuilder::forAlert($alert, 1),
            'chart7d' => AlertChartBuilder::forAlert($alert, 7),
            'chart30d' => AlertChartBuilder::forAlert($alert, 30),
        ];

        $sent24h = (int) \array_sum($chartData['chart24h']['sent']);
        $failed24h = (int) \array_sum($chartData['chart24h']['failed']);
        $total7d = (int) (\array_sum($chartData['chart7d']['sent']) + \array_sum($chartData['chart7d']['failed']));
        $total30d = (int) (\array_sum($chartData['chart30d']['sent']) + \array_sum($chartData['chart30d']['failed']));

        $statsHtml = \view('pages.alerts.partials.show.stats', [
            'failed24h' => $failed24h,
            'sent24h' => $sent24h,
            'total7d' => $total7d,
            'total30d' => $total30d,
        ])->render();

        $chartJson = \json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->buildStreams([
            ['update', 'alert-detail-stats', $statsHtml, 'morph'],
            ['replace', 'alert-detail-chart-data', "<script type='application/json' id='alert-detail-chart-data'>$chartJson</script>", null],
        ]);
    }
}
