<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Models\AlertLog;
use Carbon\Carbon;

class AlertChartDataService
{
    /**
     * Build chart data for an alert for a given window.
     *
     * @return array{xAxis: list<string>, sent: list<int>, failed: list<int>}
     */
    public function buildChart(Alert $alert, int $days): array
    {
        $since = $days === 1
            ? \now()->subDay()
            : \now()->subDays($days - 1)->startOfDay();

        $bucketFormatPhp = $days === 1 ? 'Y-m-d H:00' : 'Y-m-d';
        $bucketFormatSql = $days === 1 ? '%Y-%m-%d %H:00' : '%Y-%m-%d';

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
            ->selectRaw("DATE_FORMAT(sent_at, '$bucketFormatSql') as bucket, status, COUNT(*) as total")
            ->groupBy('bucket', 'status')
            ->get();

        foreach ($logs as $row) {
            $key = $row->bucket;
            if (! isset($buckets[$key])) {
                continue;
            }
            $status = (string) $row->status === 'sent' ? 'sent' : 'failed';
            $buckets[$key][$status] += (int) $row->total;
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
