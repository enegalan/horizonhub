<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use Carbon\Carbon;

trait BuildsAlertStreams
{
    /**
     * Build the alerts index streams.
     */
    protected function buildAlerts(): string
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

        return $this->buildStreams([
            ['update', 'turbo-horizon-alert-stats', \view('horizon.alerts.partials.index.stats', ['alertStats' => ['total' => $alerts->count(), 'enabled' => $alerts->where('enabled')->count(), 'disabled' => $alerts->where('enabled', false)->count()]])->render(), 'morph'],
            ['update', 'turbo-tbody-horizon-alerts-list', \view('horizon.alerts.partials.index.tbody', ['alerts' => $alerts, 'serviceLabelsByAlertId' => $labelsByAlertId])->render(), 'morph'],
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
            'chart24h' => $this->private__buildChart($alert, 1),
            'chart7d' => $this->private__buildChart($alert, 7),
            'chart30d' => $this->private__buildChart($alert, 30),
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

    /**
     * Build chart data for an alert for a given window.
     *
     * @param Alert $alert The alert.
     * @param int $days The number of days to build the chart for.
     *
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    private function private__buildChart(Alert $alert, int $days): array
    {
        $since = $days === 1
        ? \now()->subDay()
        : \now()->subDays($days - 1)->startOfDay();

        $bucketFormatPhp = $days === 1 ? 'Y-m-d H:00' : 'Y-m-d';

        $buckets = [];
        $totalSlots = $days === 1 ? 24 : $days;

        for ($i = 0; $i < $totalSlots; $i++) {
            $key = $days === 1
                ? \now()->subHours(23 - $i)->format($bucketFormatPhp)
                : \now()->subDays($days - 1 - $i)->format($bucketFormatPhp);
            $buckets[$key] = ['sent' => 0, 'failed' => 0];
        }

        $logs = AlertLog::where('alert_id', $alert->id)
            ->where('sent_at', '>=', $since)
            ->get(['sent_at', 'status']);

        foreach ($logs as $log) {
            $key = $days === 1
                ? $log->sent_at->copy()->startOfHour()->format($bucketFormatPhp)
                : $log->sent_at->copy()->startOfDay()->format($bucketFormatPhp);

            if (! isset($buckets[$key])) {
                continue;
            }

            $status = (string) $log->status === 'sent' ? 'sent' : 'failed';
            $buckets[$key][$status]++;
        }

        $xAxis = [];
        $sent = [];
        $failed = [];

        foreach ($buckets as $k => $v) {
            $xAxis[] = $days === 1
                ? Carbon::parse($k)->format('H:i')
                : Carbon::parse($k)->format('M j');
            $sent[] = $v['sent'];
            $failed[] = $v['failed'];
        }

        return ['xAxis' => $xAxis, 'sent' => $sent, 'failed' => $failed];
    }
}
