import { formatDateTimeElements } from '../lib/datetime-format';
import { initAlertDetailCharts } from '../charts/metrics-charts';

/**
 * Horizon alerts list.
 * @returns {object}
 */
export function horizonAlertsList() {
    return {
        /**
         * Initialize the alerts list.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshAlertsList(e.detail && e.detail.document);
            });
        },
        /**
         * Refresh the alerts list.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshAlertsList(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newTable = preloadedDoc.querySelector('[data-resizable-table="horizon-alerts-list"]');
            var currentTable = document.querySelector('[data-resizable-table="horizon-alerts-list"]');
            if (!newTable || !currentTable) return;
            var newTbody = newTable.querySelector('tbody');
            var currentTbody = currentTable.querySelector('tbody');
            if (newTbody && currentTbody) {
                currentTbody.replaceWith(newTbody);
                if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                    window.horizonInitResizableTables();
                }
                if (typeof window !== 'undefined') {
                    formatDateTimeElements(currentTable);
                }
            }
        },
    };
}

/**
 * Horizon alert detail.
 * @returns {object}
 */
export function horizonAlertDetail() {
    return {
        /**
         * Initialize the alert detail.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshAlertDetail(e.detail && e.detail.document);
            });
        },
        refreshAlertDetail(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;

            var newRoot = preloadedDoc.querySelector('[data-horizon-alert-detail-root="1"]');
            var currentRoot = document.querySelector('[data-horizon-alert-detail-root="1"]');
            if (!newRoot || !currentRoot) return;

            function replaceSection(selector) {
                var cur = currentRoot.querySelector(selector);
                var neu = newRoot.querySelector(selector);
                if (cur && neu) cur.replaceWith(neu);
            }

            replaceSection('[data-alert-detail-breadcrumb]');
            replaceSection('[data-alert-detail-stats]');
            replaceSection('[data-alert-detail-rule]');
            replaceSection('[data-alert-detail-after-charts]');

            var chartData = null;
            var scriptEl = document.getElementById('alert-detail-chart-data');
            if (scriptEl && scriptEl.textContent) {
                try {
                    chartData = JSON.parse(scriptEl.textContent.trim());
                } catch (e) {}
            }

            if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }

            formatDateTimeElements(currentRoot);

            var chartIds = ['alert-detail-chart-24h', 'alert-detail-chart-7d', 'alert-detail-chart-30d'];
            if (chartData && window.echarts) {
                chartIds.forEach(function (id) {
                    var el = currentRoot.querySelector('#' + id);
                    if (el) {
                        var instance = window.echarts.getInstanceByDom(el);
                        if (instance) instance.dispose();
                    }
                });
                requestAnimationFrame(function () {
                    initAlertDetailCharts(chartData);
                    requestAnimationFrame(function () {
                        chartIds.forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) {
                                var inst = window.echarts.getInstanceByDom(el);
                                if (inst) inst.resize();
                            }
                        });
                    });
                });
            }
        },
    };
}
