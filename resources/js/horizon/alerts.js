import { getChartColors, applyChartOptions } from '../charts/charts';
import { parseJsonFromElement } from '../lib/parse';

/**
 * Alert detail charts.
 * @type {object}
 */
const ALERT_DETAIL_CHARTS = [
    { key: 'chart24h', id: 'alert-detail-chart-24h' },
    { key: 'chart7d', id: 'alert-detail-chart-7d' },
    { key: 'chart30d', id: 'alert-detail-chart-30d' }
];

/**
 * Render the alert detail charts.
 * @returns {void}
 */
export function renderAlertDetailCharts() {
    if (typeof window.echarts === 'undefined') return;
    var data = parseJsonFromElement('alert-detail-chart-data-json');
    var ready = data && typeof data === 'object' && !Array.isArray(data) && data.chart24h && data.chart24h.xAxis && data.chart24h.xAxis.length;
    var loaderPrefix = 'alert-detail-loader-';
    var chartPrefix = 'alert-detail-chart-';
    ALERT_DETAIL_CHARTS.forEach(function (item) {
        var loader = document.getElementById(loaderPrefix + item.id.substring(chartPrefix.length));
        if (loader) {
            loader.style.display = !ready ? 'flex' : 'none';
        }
    });
    if (!data || !ready) return;

    var c = getChartColors();

    function makeBarOption(xAxis, sent, failed) {
        return {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Sent', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 8, right: 16, top: 16, bottom: 36, containLabel: true },
            xAxis: { type: 'category', data: xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: 'Sends', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Sent', data: sent, barMaxWidth: 20 },
                { type: 'bar', name: 'Failed', data: failed, barMaxWidth: 20 }
            ]
        };
    }

    for (let i = 0; i < ALERT_DETAIL_CHARTS.length; i++) {
        var { key, id } = ALERT_DETAIL_CHARTS[i];
        var chartData = data[key];
        if (!chartData) continue;
        var el = document.getElementById(id);
        if (el) applyChartOptions(el, makeBarOption(chartData.xAxis, chartData.sent, chartData.failed));
    }
}
