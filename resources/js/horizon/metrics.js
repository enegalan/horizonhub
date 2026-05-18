import { applyChartOptions, buildJobsVolumeLast24hOptions, getAxisTooltipViewportOptions, getChartColors } from "../charts/metrics-charts";
import { parseJsonFromElement } from "../lib/parse";

/**
 * Alpine component for the metrics page.
 * @returns {object}
 */
export function horizonMetricsPage() {
    return {
        /**
         * Initialize the metrics page.
         * @returns {void}
         */
        init: function () {
            if (typeof window === "undefined" || typeof document === "undefined") return;

            // Initial hydration to initially show metrics charts and format elements
            renderMetricsCharts();
        },
    };
}

/**
 * Toggle loader vs empty overlay for a chart panel.
 * @param {HTMLElement|null} loaderEl
 * @param {HTMLElement|null} emptyEl
 * @param {boolean} loaded
 * @param {boolean} hasData
 * @returns {void}
 */
function setMetricsChartPanelState(loaderEl, emptyEl, loaded, hasData) {
    if (loaderEl) {
        loaderEl.style.display = loaded ? 'none' : 'flex';
    }
    if (emptyEl) {
        if (!loaded || hasData) {
            emptyEl.style.display = 'none';
            emptyEl.setAttribute('aria-hidden', 'true');
        } else {
            emptyEl.style.display = 'flex';
            emptyEl.setAttribute('aria-hidden', 'false');
        }
    }
}

/**
 * Render the metrics charts.
 * @returns {void}
 */
export function renderMetricsCharts() {
    initMetricsCharts();
    var data = parseJsonFromElement('metrics-chart-data');
    var loaded = data && typeof data === 'object' && !Array.isArray(data);

    setMetricsChartPanelState(
        document.getElementById('metrics-loader-jobs-volume-chart'),
        document.getElementById('metrics-empty-jobs-volume-chart'),
        loaded,
        !!(data && data.jobsVolumeLast24h && data.jobsVolumeLast24h.xAxis && data.jobsVolumeLast24h.xAxis.length)
    );
    setMetricsChartPanelState(
        document.getElementById('metrics-loader-failure-rate-chart'),
        document.getElementById('metrics-empty-failure-rate-chart'),
        loaded,
        !!(data && data.failureRateOverTime && data.failureRateOverTime.xAxis && data.failureRateOverTime.xAxis.length)
    );
    setMetricsChartPanelState(
        document.getElementById('metrics-loader-runtime-chart'),
        document.getElementById('metrics-empty-runtime-chart'),
        loaded,
        (data && data.jobRuntimesLast24h && data.jobRuntimesLast24h.points ? data.jobRuntimesLast24h.points : []).length > 0
    );
    setMetricsChartPanelState(
        document.getElementById('metrics-loader-service-chart'),
        document.getElementById('metrics-empty-service-chart'),
        loaded,
        !!(data && data.waitByQueue && data.waitByQueue.queues && data.waitByQueue.queues.length)
    );
}

/**
 * Initialize the metrics charts.
 * @returns {void}
 */
function initMetricsCharts() {
    if (typeof window.echarts === 'undefined') return;
    var data = parseJsonFromElement('metrics-chart-data');
    if (!data) return;
    var c = getChartColors();

    var jobsVolumeEl = document.getElementById('jobs-volume-last-24h-chart');
    if (jobsVolumeEl && data.jobsVolumeLast24h && data.jobsVolumeLast24h.xAxis && data.jobsVolumeLast24h.xAxis.length) {
        applyChartOptions(jobsVolumeEl, buildJobsVolumeLast24hOptions(data.jobsVolumeLast24h, c));
    }

    var processedFailedEl = document.getElementById('processed-failed-chart');
    if (processedFailedEl && data.jobsPastHourByService && data.jobsPastHourByService.services && data.jobsPastHourByService.services.length) {
        applyChartOptions(processedFailedEl, {
            animation: false,
            color: [c.processed],
            tooltip: Object.assign({}, getAxisTooltipViewportOptions(), {
                trigger: 'axis',
                formatter: function (params) {
                    if (!params || !params.length) return '';
                    var p = params[0];
                    return p.name + ': ' + p.value + ' job(s)';
                }
            }),
            legend: {
                data: ['Jobs past hour'],
                bottom: 0,
                textStyle: { color: c.axis, fontSize: 10 }
            },
            grid: { left: 8, right: 16, top: 16, bottom: 36, containLabel: true },
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
    if (failureRateEl && data.failureRateOverTime && data.failureRateOverTime.xAxis && data.failureRateOverTime.xAxis.length) {
        applyChartOptions(failureRateEl, {
            animation: false,
            color: [c.failed],
            tooltip: Object.assign({}, getAxisTooltipViewportOptions(), { trigger: 'axis', formatter: '{b}: {c}%' }),
            grid: { left: 8, right: 16, top: 16, bottom: 32, containLabel: true },
            xAxis: { type: 'category', data: data.failureRateOverTime.xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: '%', min: 0, axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [{ type: 'line', name: 'Failure rate', data: data.failureRateOverTime.rate, smooth: true, symbol: 'circle', symbolSize: 4 }]
        });
    }

    var runtimeEl = document.getElementById('runtime-chart');
    if (runtimeEl && data.jobRuntimesLast24h) {
        var rawPoints = data.jobRuntimesLast24h.points || [];
        if (!rawPoints.length) {
            var existingRuntime = window.echarts.getInstanceByDom(runtimeEl);
            if (existingRuntime) {
                existingRuntime.dispose();
            }
        } else {
            function mapJobRuntimeLineVertex(p) {
                return {
                    value: [p.endAtMs, p.seconds],
                    labelName: p.name || '',
                    labelService: p.service || ''
                };
            }
            function sortJobRuntimeVerticesByTime(vertices) {
                return vertices.slice().sort(function (a, b) {
                    return a.value[0] - b.value[0];
                });
            }
            var completedPts = sortJobRuntimeVerticesByTime(
                rawPoints.filter(function (p) { return p.status === 'completed'; }).map(mapJobRuntimeLineVertex)
            );
            var failedPts = sortJobRuntimeVerticesByTime(
                rawPoints.filter(function (p) { return p.status === 'failed'; }).map(mapJobRuntimeLineVertex)
            );
            var series = [];
            if (completedPts.length) {
                series.push({
                    type: 'line',
                    name: 'Completed',
                    data: completedPts,
                    smooth: false,
                    showSymbol: false,
                    lineStyle: { width: 2, color: c.processed },
                    itemStyle: { color: c.processed }
                });
            }
            if (failedPts.length) {
                series.push({
                    type: 'line',
                    name: 'Failed',
                    data: failedPts,
                    smooth: false,
                    showSymbol: false,
                    lineStyle: { width: 2, color: c.failed },
                    itemStyle: { color: c.failed }
                });
            }
            if (series.length) {
                applyChartOptions(runtimeEl, {
                    animation: false,
                    tooltip: Object.assign({}, getAxisTooltipViewportOptions(), {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'line',
                            lineStyle: { color: c.axis, opacity: 0.45 }
                        },
                        formatter: function (params) {
                            if (!params || !params.length) return '';
                            var blocks = [];
                            for (var i = 0; i < params.length; i++) {
                                var param = params[i];
                                var v = param.value;
                                if (!v || v.length < 2) continue;
                                var timeStr = new Date(v[0]).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
                                var jobName = param.data && param.data.labelName ? param.data.labelName : '—';
                                var svc = param.data && param.data.labelService ? param.data.labelService : '';
                                var lines = [
                                    param.marker + param.seriesName,
                                    jobName,
                                    v[1] + ' s',
                                    timeStr
                                ];
                                if (svc) {
                                    lines.splice(2, 0, svc);
                                }
                                blocks.push(lines.join('<br/>'));
                            }
                            return blocks.length ? blocks.join('<br/><br/>') : '';
                        }
                    }),
                    legend: {
                        data: series.map(function (s) { return s.name; }),
                        bottom: 0,
                        textStyle: { color: c.axis, fontSize: 10 }
                    },
                    grid: { left: 8, right: 16, top: 16, bottom: 36, containLabel: true },
                    xAxis: {
                        type: 'time',
                        axisLine: { lineStyle: { color: c.axis } },
                        axisLabel: { color: c.axis, fontSize: 10 },
                        splitLine: { show: false }
                    },
                    yAxis: {
                        type: 'value',
                        name: 's',
                        min: 0,
                        scale: true,
                        axisLine: { show: false },
                        splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } },
                        axisLabel: { color: c.axis, fontSize: 10 }
                    },
                    series: series
                });
            }
        }
    }

    var serviceEl = document.getElementById('service-distribution-chart');
    if (serviceEl && data.waitByQueue && data.waitByQueue.queues && data.waitByQueue.queues.length) {
        applyChartOptions(serviceEl, {
            animation: false,
            color: [c.processed],
            tooltip: Object.assign({}, getAxisTooltipViewportOptions(), {
                trigger: 'axis',
                formatter: function (params) {
                    if (!params || !params.length) return '';
                    var p = params[0];
                    return p.name + ': ' + p.value.toFixed(2) + ' s';
                }
            }),
            legend: { data: [], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 8, right: 16, top: 16, bottom: 36, containLabel: true },
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
