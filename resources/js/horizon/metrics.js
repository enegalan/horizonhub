import { applyChartOptions, getChartColors } from "../charts/metrics-charts";
import { parseJsonFromElement } from "../lib/parse";
import { isHotReloadEnabled } from "../lib/sse";


/**
 * Refresh the service_id / service_id[] search params in a URL object.
 * @param {string[]} ids
 * @param {URL} url
 * @returns {void}
 */
function refreshServiceIdSearchParams(ids, url) {
    url.searchParams.delete("service_id");
    url.searchParams.delete("service_id[]");
    ids.forEach(function (id) {
        url.searchParams.append("service_id[]", id);
    });
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
                    tooltip: {
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
                    },
                    legend: {
                        data: series.map(function (s) { return s.name; }),
                        bottom: 0,
                        textStyle: { color: c.axis, fontSize: 10 }
                    },
                    grid: { left: 48, right: 24, top: 16, bottom: 36 },
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
 * Read selected service ids from the metrics multiselect (hidden inputs).
 * @param {HTMLElement|null} filterEl
 * @returns {string[]}
 */
function getMetricsServiceIdsFromDom(filterEl) {
    if (!filterEl) return [];
    var inputs = filterEl.querySelectorAll('input[type="hidden"][name="service_id[]"]');
    var out = [];
    inputs.forEach(function (inp) {
        if (inp && inp.value) out.push(String(inp.value));
    });
    out.sort();
    return out;
}

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
            initMetricsCharts();

            var filterEl = document.getElementById("metrics-service-filter");
            if (filterEl) {
                filterEl.addEventListener("change", function (e) {
                    var ids = e.detail.values.map(String).filter(Boolean).sort();
                    var url = new URL(window.location.href);
                    refreshServiceIdSearchParams(ids, url);
                    window.history.replaceState({}, "", url.toString());
                    if (isHotReloadEnabled() && typeof window.__horizonHubRefreshStreamReconnect === "function") {
                        window.__horizonHubRefreshStreamReconnect();
                    }
                });
            }
        },
    };
}
