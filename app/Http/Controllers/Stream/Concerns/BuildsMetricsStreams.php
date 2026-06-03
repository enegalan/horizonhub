<?php

namespace App\Http\Controllers\Stream\Concerns;

trait BuildsMetricsStreams
{
    /**
     * Build the metrics streams.
     *
     * @param string $query The query.
     */
    private function buildMetrics(string $query): string
    {
        $d = $this->metrics->buildMetricsDashboardData($this->serviceFilter->resolveFromQuery($query));

        $failureRateHtml = \view('horizon.metrics.partials.index.failure-rate-value', [
            'failureRate24h' => $d['failureRate24h'],
        ])->render();

        $chartJson = \json_encode($d['metricsChartData'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->buildStreams([
            ['update', 'metrics-value-jobs-minute', e($d['jobsPastMinute'] ?? '—'), null],
            ['update', 'metrics-value-jobs-hour', e($d['jobsPastHour'] ?? '—'), null],
            ['update', 'metrics-value-failed-seven', e($d['failedPastSevenDays'] ?? '—'), null],
            ['update', 'metrics-workload-summary', e($d['workloadSummary']), null],
            ['update', 'metrics-supervisors-summary', e($d['supervisorsSummary']), null],
            ['update', 'metrics-value-failure-rate', $failureRateHtml, null],
            ['replace', 'metrics-chart-data', '<script type="application/json" id="metrics-chart-data">' . $chartJson . '</script>', null],
            ['update', 'metrics-workload-body', \view('horizon.metrics.partials.index.workload-tbody', ['workloadRows' => $d['workloadRows']])->render(), 'morph'],
            ['update', 'metrics-supervisors-body', \view('horizon.metrics.partials.index.supervisors-tbody', ['supervisorsRows' => $d['supervisorsRows']])->render(), 'morph'],
        ]);
    }
}
