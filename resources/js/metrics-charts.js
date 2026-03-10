import { getCssHsl } from './utils/styles';

function getChartColors() {
    return {
        axis: getCssHsl('--muted-foreground'),
        processed: getCssHsl('--primary'),
        failed: getCssHsl('--destructive'),
        line: getCssHsl('--muted-foreground'),
    };
}

export function initMetricsCharts(data) {
    if (typeof window.echarts === 'undefined' || !data) return;

    var c = getChartColors();

    var processedFailedEl = document.getElementById('processed-failed-chart');
    if (processedFailedEl && data.processedVsFailed) {
        applyChartOptions(processedFailedEl, {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 48, right: 24, top: 16, bottom: 36 },
            xAxis: { type: 'category', data: data.processedVsFailed.xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: 'Jobs', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.processedVsFailed.processed, barMaxWidth: 20 },
                { type: 'bar', name: 'Failed', data: data.processedVsFailed.failed, barMaxWidth: 20 }
            ]
        });
    }

    var failureRateEl = document.getElementById('failure-rate-chart');
    if (failureRateEl && data.failureRateOverTime) {
        applyChartOptions(failureRateEl, {
            animation: false,
            color: [c.failed],
            tooltip: { trigger: 'axis', formatter: '{b}: {c}%' },
            grid: { left: 48, right: 24, top: 16, bottom: 32 },
            xAxis: { type: 'category', data: data.failureRateOverTime.xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: '%', min: 0, axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [{ type: 'line', name: 'Failure rate', data: data.failureRateOverTime.rate, smooth: true, symbol: 'circle', symbolSize: 4 }]
        });
    }

    var runtimeEl = document.getElementById('runtime-chart');
    if (runtimeEl && data.avgRuntimeOverTime) {
        applyChartOptions(runtimeEl, {
            animation: false,
            color: [c.line],
            tooltip: { trigger: 'axis', formatter: params => { var v = params[0].value; return params[0].axisValue + ': ' + (v != null ? v + 's' : '–'); } },
            grid: { left: 48, right: 24, top: 16, bottom: 32 },
            xAxis: { type: 'category', data: data.avgRuntimeOverTime.xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: 's', min: 0, axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [{ type: 'line', name: 'Avg runtime', data: data.avgRuntimeOverTime.avgSeconds, smooth: true, symbol: 'circle', symbolSize: 4 }]
        });
    }

    var queueEl = document.getElementById('queue-distribution-chart');
    if (queueEl && data.byQueue && data.byQueue.queues && data.byQueue.queues.length) {
        applyChartOptions(queueEl, {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 120, right: 48, top: 16, bottom: 36 },
            xAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'category', data: data.byQueue.queues, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.byQueue.processed, barMaxWidth: 16 },
                { type: 'bar', name: 'Failed', data: data.byQueue.failed, barMaxWidth: 16 }
            ]
        });
    }

    var serviceEl = document.getElementById('service-distribution-chart');
    if (serviceEl && data.byService && data.byService.services && data.byService.services.length) {
        applyChartOptions(serviceEl, {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 120, right: 48, top: 16, bottom: 36 },
            xAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'category', data: data.byService.services, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.byService.processed, barMaxWidth: 16 },
                { type: 'bar', name: 'Failed', data: data.byService.failed, barMaxWidth: 16 }
            ]
        });
    }
}

var ALERT_DETAIL_CHARTS = [
    { key: 'chart24h', id: 'alert-detail-chart-24h' },
    { key: 'chart7d', id: 'alert-detail-chart-7d' },
    { key: 'chart30d', id: 'alert-detail-chart-30d' }
];

export function initAlertDetailCharts(data) {
    if (typeof window.echarts === 'undefined' || !data) return;

    var c = getChartColors();

    function makeBarOption(xAxis, sent, failed) {
        return {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Sent', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 48, right: 24, top: 16, bottom: 36 },
            xAxis: { type: 'category', data: xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: 'Sends', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Sent', data: sent, barMaxWidth: 20 },
                { type: 'bar', name: 'Failed', data: failed, barMaxWidth: 20 }
            ]
        };
    }

    for (var i = 0; i < ALERT_DETAIL_CHARTS.length; i++) {
        var { key, id } = ALERT_DETAIL_CHARTS[i];
        var chartData = data[key];
        if (!chartData) continue;
        var el = document.getElementById(id);
        if (el) applyChartOptions(el, makeBarOption(chartData.xAxis, chartData.sent, chartData.failed));
    }
}

function applyChartOptions(el, options) {
    var existing = window.echarts.getInstanceByDom(el);
    if (existing) {
        existing.setOption(options);
        existing.resize();
    } else {
        var chart = window.echarts.init(el);
        chart.setOption(options);
        chart.resize();
    }
}
