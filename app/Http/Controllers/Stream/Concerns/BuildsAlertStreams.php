<?php

namespace App\Http\Controllers\Stream\Concerns;

use App\Enums\AlertLogStatus;
use App\Models\Alert;
use App\Models\AlertLog;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Carbon\Carbon;

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
            'chart24h' => $this->private__buildChart($alert, 1),
            'chart7d' => $this->private__buildChart($alert, 7),
            'chart30d' => $this->private__buildChart($alert, 30),
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

            $status = $log->status === AlertLogStatus::Sent ? 'sent' : 'failed';
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
