import './bootstrap';
import './resizable-table';
import './alert-log-modal';

import { createRoot } from 'react-dom/client';
import React from 'react';
import { Toaster, toast } from 'sonner';
import 'sonner/dist/styles.css';

(function mountToaster() {
    function run() {
        var el = document.getElementById('toaster');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toaster';
            el.setAttribute('aria-live', 'polite');
            if (document.body) {
                document.body.appendChild(el);
            } else {
                document.addEventListener('DOMContentLoaded', run);
                return;
            }
        }
        if (el._toasterMounted) return;
        el._toasterMounted = true;
        try {
            var root = createRoot(el);
            root.render(React.createElement(Toaster, {
                theme: 'system',
                richColors: true,
                position: 'bottom-right',
            }));
            window.toast = toast;
        } catch (err) {
            console.error('Toaster mount failed', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    document.addEventListener('livewire:navigated', function () {
        run();
    });
})();

(function toastListeners() {
    function showToast(type, message) {
        if (!window.toast) return;
        var t = (type === 'error' || type === 'success' || type === 'info' || type === 'warning') ? type : 'success';
        var msg = typeof message === 'string' ? message : 'Done.';
        window.toast[t](msg);
    }
    function onToast(e) {
        var d = e && e.detail;
        if (!d) return;
        if (typeof d === 'object' && ('type' in d || 'message' in d)) {
            showToast(d.type, d.message);
        } else if (typeof d === 'string') {
            showToast('success', d);
        }
    }
    function addListeners() {
        var opts = { capture: true };
        window.addEventListener('toast', onToast, opts);
        window.addEventListener('service-created', function () { showToast('success', 'Service registered.'); }, opts);
        window.addEventListener('job-retried', function () { showToast('success', 'Job retried.'); }, opts);
        window.addEventListener('job-action-failed', function (e) {
            showToast('error', (e && e.detail && e.detail.message) || 'Action failed.');
        }, opts);
        window.addEventListener('queue-updated', function () { showToast('success', 'Queue updated.'); }, opts);
        window.addEventListener('alerts-saved', function () { showToast('success', 'Alerts saved.'); }, opts);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addListeners);
    } else {
        addListeners();
    }
})();

(function () {
    function getResolvedDark() {
        var t = localStorage.getItem('horizonhub_theme');
        if (!t) t = localStorage.getItem('horizonhub_dark') === 'true' ? 'dark' : 'light';
        if (t === 'system') return window.matchMedia('(prefers-color-scheme: dark)').matches;
        return t === 'dark';
    }
    function applyTheme() {
        document.documentElement.classList.toggle('dark', getResolvedDark());
    }
    applyTheme();
    document.addEventListener('livewire:navigating', function (e) {
        e.detail.onSwap(applyTheme);
    });
    window.addEventListener('apply-theme', applyTheme);
})();

(function () {
    function initMetricsCharts() {
        if (typeof window.echarts === 'undefined') return;
        var dataEl = document.getElementById('metrics-chart-data');
        if (!dataEl) return;
        var data;
        try {
            data = JSON.parse(dataEl.textContent);
        } catch (e) {
            return;
        }
        var isDark = document.documentElement.classList.contains('dark');
        var axisColor = isDark ? '#71717a' : '#a1a1aa';
        var processedColor = isDark ? '#22c55e' : '#16a34a';
        var failedColor = isDark ? '#ef4444' : '#dc2626';
        var lineColor = isDark ? '#a1a1aa' : '#3f3f46';

        var opt1 = {
            animation: false,
            color: [processedColor, failedColor],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: axisColor, fontSize: 10 } },
            grid: { left: 48, right: 24, top: 16, bottom: 36 },
            xAxis: { type: 'category', data: data.processedVsFailed.xAxis, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
            yAxis: { type: 'value', name: 'Jobs', axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.processedVsFailed.processed, barMaxWidth: 20 },
                { type: 'bar', name: 'Failed', data: data.processedVsFailed.failed, barMaxWidth: 20 },
            ],
        };
        var processedFailedEl = document.getElementById('processed-failed-chart');
        if (processedFailedEl && data.processedVsFailed) {
            var existing1 = window.echarts.getInstanceByDom(processedFailedEl);
            if (existing1) {
                existing1.setOption(opt1);
            } else {
                window.echarts.init(processedFailedEl).setOption(opt1);
            }
        }

        var opt2 = {
            animation: false,
            color: [failedColor],
            tooltip: { trigger: 'axis', formatter: '{b}: {c}%' },
            grid: { left: 48, right: 24, top: 16, bottom: 32 },
            xAxis: { type: 'category', data: data.failureRateOverTime.xAxis, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
            yAxis: { type: 'value', name: '%', min: 0, axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
            series: [{ type: 'line', name: 'Failure rate', data: data.failureRateOverTime.rate, smooth: true, symbol: 'circle', symbolSize: 4 }],
        };
        var failureRateEl = document.getElementById('failure-rate-chart');
        if (failureRateEl && data.failureRateOverTime) {
            var existing2 = window.echarts.getInstanceByDom(failureRateEl);
            if (existing2) {
                existing2.setOption(opt2);
            } else {
                window.echarts.init(failureRateEl).setOption(opt2);
            }
        }

        var opt3 = {
            animation: false,
            color: [lineColor],
            tooltip: { trigger: 'axis', formatter: function (params) { var v = params[0].value; return params[0].axisValue + ': ' + (v != null ? v + 's' : '–'); } },
            grid: { left: 48, right: 24, top: 16, bottom: 32 },
            xAxis: { type: 'category', data: data.avgRuntimeOverTime.xAxis, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
            yAxis: { type: 'value', name: 's', min: 0, axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
            series: [{ type: 'line', name: 'Avg runtime', data: data.avgRuntimeOverTime.avgSeconds, smooth: true, symbol: 'circle', symbolSize: 4 }],
        };
        var runtimeEl = document.getElementById('runtime-chart');
        if (runtimeEl && data.avgRuntimeOverTime) {
            var existing3 = window.echarts.getInstanceByDom(runtimeEl);
            if (existing3) {
                existing3.setOption(opt3);
            } else {
                window.echarts.init(runtimeEl).setOption(opt3);
            }
        }

        var opt4 = {
            animation: false,
            color: [processedColor, failedColor],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: axisColor, fontSize: 10 } },
            grid: { left: 120, right: 48, top: 16, bottom: 36 },
            xAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
            yAxis: { type: 'category', data: data.byQueue.queues, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.byQueue.processed, barMaxWidth: 16 },
                { type: 'bar', name: 'Failed', data: data.byQueue.failed, barMaxWidth: 16 },
            ],
        };
        var queueEl = document.getElementById('queue-distribution-chart');
        if (queueEl && data.byQueue && data.byQueue.queues && data.byQueue.queues.length) {
            var existing4 = window.echarts.getInstanceByDom(queueEl);
            if (existing4) {
                existing4.setOption(opt4);
            } else {
                window.echarts.init(queueEl).setOption(opt4);
            }
        }

        var opt5 = {
            animation: false,
            color: [processedColor, failedColor],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Processed', 'Failed'], bottom: 0, textStyle: { color: axisColor, fontSize: 10 } },
            grid: { left: 120, right: 48, top: 16, bottom: 36 },
            xAxis: { type: 'value', axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
            yAxis: { type: 'category', data: data.byService.services, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Processed', data: data.byService.processed, barMaxWidth: 16 },
                { type: 'bar', name: 'Failed', data: data.byService.failed, barMaxWidth: 16 },
            ],
        };
        var serviceEl = document.getElementById('service-distribution-chart');
        if (serviceEl && data.byService && data.byService.services && data.byService.services.length) {
            var existing5 = window.echarts.getInstanceByDom(serviceEl);
            if (existing5) {
                existing5.setOption(opt5);
            } else {
                window.echarts.init(serviceEl).setOption(opt5);
            }
        }
    }

    function initAlertDetailCharts() {
        if (typeof window.echarts === 'undefined') return;
        var dataEl = document.getElementById('alert-detail-chart-data');
        if (!dataEl) return;
        var data;
        try {
            data = JSON.parse(dataEl.textContent);
        } catch (e) {
            return;
        }
        var isDark = document.documentElement.classList.contains('dark');
        var axisColor = isDark ? '#71717a' : '#a1a1aa';
        var sentColor = isDark ? '#22c55e' : '#16a34a';
        var failedColor = isDark ? '#ef4444' : '#dc2626';

        function makeBarOption(xAxis, sent, failed) {
            return {
                animation: false,
                color: [sentColor, failedColor],
                tooltip: { trigger: 'axis' },
                legend: { data: ['Sent', 'Failed'], bottom: 0, textStyle: { color: axisColor, fontSize: 10 } },
                grid: { left: 48, right: 24, top: 16, bottom: 36 },
                xAxis: { type: 'category', data: xAxis, axisLine: { lineStyle: { color: axisColor } }, axisLabel: { color: axisColor, fontSize: 10 } },
                yAxis: { type: 'value', name: 'Sends', axisLine: { show: false }, splitLine: { lineStyle: { color: axisColor, opacity: 0.3 } }, axisLabel: { color: axisColor, fontSize: 10 } },
                series: [
                    { type: 'bar', name: 'Sent', data: sent, barMaxWidth: 20 },
                    { type: 'bar', name: 'Failed', data: failed, barMaxWidth: 20 },
                ],
            };
        }

        var chart24h = data.chart24h;
        if (chart24h) {
            var el24 = document.getElementById('alert-detail-chart-24h');
            if (el24) {
                var opt24 = makeBarOption(chart24h.xAxis, chart24h.sent, chart24h.failed);
                var existing = window.echarts.getInstanceByDom(el24);
                if (existing) {
                    existing.setOption(opt24);
                } else {
                    window.echarts.init(el24).setOption(opt24);
                }
            }
        }
        var chart7d = data.chart7d;
        if (chart7d) {
            var el7 = document.getElementById('alert-detail-chart-7d');
            if (el7) {
                var opt7 = makeBarOption(chart7d.xAxis, chart7d.sent, chart7d.failed);
                var existing7 = window.echarts.getInstanceByDom(el7);
                if (existing7) {
                    existing7.setOption(opt7);
                } else {
                    window.echarts.init(el7).setOption(opt7);
                }
            }
        }
        var chart30d = data.chart30d;
        if (chart30d) {
            var el30 = document.getElementById('alert-detail-chart-30d');
            if (el30) {
                var opt30 = makeBarOption(chart30d.xAxis, chart30d.sent, chart30d.failed);
                var existing30 = window.echarts.getInstanceByDom(el30);
                if (existing30) {
                    existing30.setOption(opt30);
                } else {
                    window.echarts.init(el30).setOption(opt30);
                }
            }
        }
    }

    function scheduleMetricsCharts() {
        setTimeout(function () {
            initMetricsCharts();
            initAlertDetailCharts();
        }, 0);
    }

    document.addEventListener('DOMContentLoaded', scheduleMetricsCharts);
    document.addEventListener('livewire:navigated', scheduleMetricsCharts);
    document.addEventListener('livewire:initialized', function () {
        if (typeof window.Livewire === 'undefined') return;
        window.Livewire.hook('request', function (ref) {
            var succeed = ref.succeed;
            if (succeed) succeed(scheduleMetricsCharts);
        });
        window.Livewire.hook('morph.updated', function () {
            if (document.getElementById('alert-detail-chart-data')) {
                initAlertDetailCharts();
            }
        });
    });
})();

(function () {
    function formatDateTimeElements() {
        var els = document.querySelectorAll('[data-datetime]');
        if (!els || !els.length) return;
        els.forEach(function (el) {
            var iso = el.getAttribute('data-datetime');
            if (!iso) return;
            try {
                var d = new Date(iso);
                if (isNaN(d.getTime())) return;
                function pad(n) { return n < 10 ? '0' + n : '' + n; }
                var year = d.getFullYear();
                var month = pad(d.getMonth() + 1);
                var day = pad(d.getDate());
                var hour = pad(d.getHours());
                var minute = pad(d.getMinutes());
                var second = pad(d.getSeconds());
                var formatted = year + '-' + month + '-' + day + ' ' + hour + ':' + minute + ':' + second;
                el.textContent = formatted;
            } catch (e) {
            }
        });
    }

    function scheduleFormat() {
        setTimeout(formatDateTimeElements, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleFormat);
    } else {
        scheduleFormat();
    }

    document.addEventListener('livewire:navigated', scheduleFormat);
    document.addEventListener('livewire:initialized', function () {
        if (typeof window.Livewire === 'undefined') return;
        window.Livewire.hook('request', function (ref) {
            var succeed = ref.succeed;
            if (succeed) succeed(scheduleFormat);
        });
        window.Livewire.hook('morph.updated', scheduleFormat);
    });
})();
