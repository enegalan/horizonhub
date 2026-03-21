import { getCssHsl } from '../utils/styles';
import { parseJsonFromElement } from '../utils/parse';

/**
 * Get the chart colors.
 * @returns {object}
 */
function getChartColors() {
    return {
        axis: getCssHsl('--muted-foreground'),
        processed: getCssHsl('--primary'),
        failed: getCssHsl('--destructive'),
        line: getCssHsl('--muted-foreground'),
    };
}

/**
 * Initialize the metrics charts.
 * @param {object} data
 * @returns {void}
 */
function initMetricsCharts(data) {
    if (typeof window.echarts === 'undefined' || !data) return;

    var c = getChartColors();

    var jobsVolumeEl = document.getElementById('jobs-volume-last-24h-chart');
    if (jobsVolumeEl && data.jobsVolumeLast24h && data.jobsVolumeLast24h.xAxis && data.jobsVolumeLast24h.xAxis.length) {
        applyChartOptions(jobsVolumeEl, {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: {
                data: ['Completed', 'Failed'],
                bottom: 0,
                textStyle: { color: c.axis, fontSize: 10 }
            },
            grid: { left: 48, right: 24, top: 16, bottom: 36 },
            xAxis: {
                type: 'category',
                data: data.jobsVolumeLast24h.xAxis,
                axisLine: { lineStyle: { color: c.axis } },
                axisLabel: { color: c.axis, fontSize: 10 }
            },
            yAxis: {
                type: 'value',
                name: 'Jobs',
                minInterval: 1,
                axisLine: { show: false },
                splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } },
                axisLabel: { color: c.axis, fontSize: 10 }
            },
            series: [
                {
                    type: 'line',
                    name: 'Completed',
                    data: data.jobsVolumeLast24h.completed,
                    smooth: false,
                    showSymbol: false,
                    lineStyle: { width: 2 }
                },
                {
                    type: 'line',
                    name: 'Failed',
                    data: data.jobsVolumeLast24h.failed,
                    smooth: false,
                    showSymbol: false,
                    lineStyle: { width: 2 }
                }
            ]
        });
    }

    var processedFailedEl = document.getElementById('processed-failed-chart');
    if (processedFailedEl && data.jobsPastHourByService && data.jobsPastHourByService.services && data.jobsPastHourByService.services.length) {
        applyChartOptions(processedFailedEl, {
            animation: false,
            color: [c.processed],
            tooltip: {
                trigger: 'axis',
                formatter: function (params) {
                    if (!params || !params.length) return '';
                    var p = params[0];
                    return p.name + ': ' + p.value + ' job(s)';
                }
            },
            legend: {
                data: ['Jobs past hour'],
                bottom: 0,
                textStyle: { color: c.axis, fontSize: 10 }
            },
            grid: { left: 48, right: 24, top: 16, bottom: 36 },
            xAxis: {
                type: 'category',
                data: data.jobsPastHourByService.services,
                axisLine: { lineStyle: { color: c.axis } },
                axisLabel: { color: c.axis, fontSize: 10, rotate: 30 }
            },
            yAxis: {
                type: 'value',
                name: 'Jobs',
                axisLine: { show: false },
                splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } },
                axisLabel: { color: c.axis, fontSize: 10 }
            },
            series: [
                {
                    type: 'line',
                    name: 'Jobs past hour',
                    data: data.jobsPastHourByService.jobsPastHour,
                    smooth: true,
                    symbol: 'circle',
                    symbolSize: 4
                }
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

    var serviceEl = document.getElementById('service-distribution-chart');
    if (serviceEl && data.waitByQueue && data.waitByQueue.queues && data.waitByQueue.queues.length) {
        applyChartOptions(serviceEl, {
            animation: false,
            color: [c.line],
            tooltip: {
                trigger: 'axis',
                formatter: function (params) {
                    if (!params || !params.length) return '';
                    var p = params[0];
                    return p.name + ': ' + p.value.toFixed(2) + ' s';
                }
            },
            legend: { data: [], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 120, right: 48, top: 16, bottom: 36 },
            xAxis: {
                type: 'value',
                name: 'Seconds',
                axisLine: { show: false },
                splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } },
                axisLabel: { color: c.axis, fontSize: 10 }
            },
            yAxis: {
                type: 'category',
                data: data.waitByQueue.queues,
                axisLine: { lineStyle: { color: c.axis } },
                axisLabel: { color: c.axis, fontSize: 10 }
            },
            series: [
                { type: 'bar', name: 'Wait', data: data.waitByQueue.wait, barMaxWidth: 16 }
            ]
        });
    }
}

/**
 * Alert detail charts.
 * @type {object}
 */
var ALERT_DETAIL_CHARTS = [
    { key: 'chart24h', id: 'alert-detail-chart-24h' },
    { key: 'chart7d', id: 'alert-detail-chart-7d' },
    { key: 'chart30d', id: 'alert-detail-chart-30d' }
];

/**
 * Initialize the alert detail charts.
 * @param {object} data
 * @returns {void}
 */
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

/**
 * Apply the chart options.
 * @param {Element} el
 * @param {object} options
 * @returns {void}
 */
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

/**
 * Hydrate the metrics charts from the DOM.
 * @returns {void}
 */
export function hydrateMetricsChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;
    var data = parseJsonFromElement('metrics-chart-data');
    if (!data) return;
    initMetricsCharts(data);
}

/**
 * Process the metrics chart queue.
 * @returns {void}
 */
export function processMetricsChartQueue() {
    if (typeof window.echarts === 'undefined') return;
    var queue = window.__metricsChartQueue;
    if (!queue || !queue.length) return;
    requestAnimationFrame(function () {
        while (queue.length) {
            var data = queue.shift();
            if (data) initMetricsCharts(data);
        }
    });
}

/**
 * Hydrate the alert detail charts from the DOM.
 * @returns {void}
 */
export function hydrateAlertDetailChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;
    var data = parseJsonFromElement('alert-detail-chart-data');
    if (!data) return;
    initAlertDetailCharts(data);
}
