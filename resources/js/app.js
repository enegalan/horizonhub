import './bootstrap';
import './resizable-table';
import { horizonJobsPage, horizonJobDetail } from './horizon-jobs';
import { horizonServiceDashboard, horizonServiceList } from './horizon-services';
import { horizonQueueList } from './horizon-queues';

import Alpine from 'alpinejs';
import { createRoot } from 'react-dom/client';
import React from 'react';
import { Toaster, toast } from 'sonner';
import 'sonner/dist/styles.css';
import moment from 'moment';
import { onDocumentReady, schedule } from './utils/init';
import { parseJsonFromElement } from './utils/parse';
import { initMetricsCharts, initAlertDetailCharts } from './metrics-charts';
import { registerToastEventListeners } from './toast-events';
import { applyTheme } from './theme';
import { formatDateTimeElements } from './datetime-format';

if (!window.moment) {
    window.moment = moment;
}

function mountToaster() {
    function run() {
        var el = document.getElementById('toaster');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toaster';
            el.setAttribute('aria-live', 'polite');
            if (document.body) document.body.appendChild(el);
            else {
                document.addEventListener('DOMContentLoaded', run);
                return;
            }
        }
        if (el._toasterMounted) return;

        el._toasterMounted = true;
        try {
            var root = createRoot(el);
            root.render(React.createElement(Toaster, {
                theme: 'light',
                richColors: true,
                position: 'bottom-right'
            }));
            window.toast = toast;
        } catch (err) {
            console.error('Toaster mount failed', err);
        }
    }
    run();
}

function hydratePage() {
    schedule(() => {
        hydrateMetricsChartsFromDom();
        hydrateAlertDetailChartsFromDom();
        formatDateTimeElements();
        formatQueueWaitElements();
    });
}

function syncTheme() {
    applyTheme();
    window.dispatchEvent(new CustomEvent('apply-theme'));
}

function getCsrfToken() {
    var token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

function defaultApiErrorHandler(error) {
    var message = 'Request failed';
    if (error && error.response && error.response.data && error.response.data.message) {
        message = error.response.data.message;
    }
    if (window.toast && window.toast.error) {
        window.toast.error(message);
    } else {
        alert(message);
    }
}

function createHttpHelpers() {
    function request(method, url, data, config) {
        if (!window.axios) {
            return Promise.reject(new Error('axios is not available'));
        }

        var finalConfig = Object.assign(
            {
                method: method,
                url: url,
                data: data || {},
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            },
            config || {},
        );

        return window.axios(finalConfig)
            .then(function (response) {
                return response.data;
            })
            .catch(function (error) {
                defaultApiErrorHandler(error);
                throw error;
            });
    }

    return {
        get: function (url, config) {
            return request('get', url, null, config);
        },
        post: function (url, data, config) {
            return request('post', url, data, config);
        },
        delete: function (url, config) {
            return request('delete', url, null, config);
        },
    };
}

if (!window.horizon) {
    window.horizon = {};
}

window.horizon.http = createHttpHelpers();

// Expose page helpers globally for Alpine x-data initialisation.
window.horizonJobsPage = horizonJobsPage;
window.horizonJobDetail = horizonJobDetail;
window.horizonServiceDashboard = horizonServiceDashboard;
window.horizonServiceList = horizonServiceList;
window.horizonQueueList = horizonQueueList;
window.hydrateMetricsChartsFromDom = hydrateMetricsChartsFromDom;
window.initMetricsCharts = initMetricsCharts;
window.processMetricsChartQueue = processMetricsChartQueue;
if (window.__metricsChartQueue && window.__metricsChartQueue.length) {
    processMetricsChartQueue();
}

window.Alpine = Alpine;
Alpine.start();

onDocumentReady(() => {
    mountToaster();
    registerToastEventListeners();
    syncTheme();
    hydratePage();
    observeQueueWaitElements();
});

window.addEventListener('apply-theme', () => {
    applyTheme();
});

function formatQueueWaitElements(root) {
    if (typeof window.moment === 'undefined') return;

    var context = root && typeof root.querySelectorAll === 'function' ? root : document;
    if (!context) return;

    var nodes = context.querySelectorAll('[data-wait-seconds]');
    if (!nodes || !nodes.length) return;

    nodes.forEach(function (el) {
        var raw = el.getAttribute('data-wait-seconds');
        if (!raw) {
            return;
        }

        var seconds = parseFloat(raw);
        if (!isFinite(seconds) || seconds < 0) {
            return;
        }

        var text = window.moment.duration(seconds, 'seconds').humanize();
        if (!text) {
            return;
        }

        text = text.replace(/^(.)/g, function ($1) {
            return $1.toUpperCase();
        });

        el.textContent = text;
    });
}

function observeQueueWaitElements() {
    if (typeof MutationObserver === 'undefined' || typeof document === 'undefined') return;
    if (document.__queueWaitObserverInitialized) return;

    var target = document.documentElement || document.body;
    if (!target) return;

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (!mutation.addedNodes || !mutation.addedNodes.length) return;

            mutation.addedNodes.forEach(function (node) {
                if (!node || node.nodeType !== 1) return;

                if (node.hasAttribute && node.hasAttribute('data-wait-seconds')) {
                    formatQueueWaitElements(node);
                } else if (typeof node.querySelectorAll === 'function') {
                    formatQueueWaitElements(node);
                }
            });
        });
    });

    observer.observe(target, { childList: true, subtree: true });
    document.__queueWaitObserverInitialized = true;
}

if (!window.formatQueueWaitElements) {
    window.formatQueueWaitElements = formatQueueWaitElements;
}

function hydrateMetricsChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;

    var data = parseJsonFromElement('metrics-chart-data');
    if (!data) return;

    initMetricsCharts(data);
}

function processMetricsChartQueue() {
    if (typeof window.echarts === 'undefined' || typeof initMetricsCharts !== 'function') return;
    var queue = window.__metricsChartQueue;
    if (!queue || !queue.length) return;
    requestAnimationFrame(function () {
        while (queue.length) {
            var data = queue.shift();
            if (data) initMetricsCharts(data);
        }
    });
}

function hydrateAlertDetailChartsFromDom() {
    if (typeof window.echarts === 'undefined') return;

    var data = parseJsonFromElement('alert-detail-chart-data');
    if (!data) return;

    initAlertDetailCharts(data);
}
