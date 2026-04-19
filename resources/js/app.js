import './bootstrap';
import './components/resizable-table';
import * as echarts from 'echarts';
import * as Turbo from '@hotwired/turbo';
import { horizonJobsPage, horizonJobDetail, horizonJobRowRetry, initJsonTrees } from './horizon/jobs';
import { horizonAlertsList, horizonAlertDetail } from './horizon/alerts';
import { horizonMetricsPage } from './horizon/metrics';
import { initTurboStream } from './lib/sse';
import { createHttpHelpers } from './lib/http';
import { formatQueueWaitElements } from './lib/datetime-format';
import { getTurboStreamTargetElement, renderTurboStreamWithGuards } from './lib/stream-guard';
import { mountToaster } from './components/toaster';
import { registerInputDatePicker } from './components/input-date-picker';
import Alpine from 'alpinejs';
import moment from 'moment';
import { initTheme } from './components/theme';

window.Turbo = Turbo;
window.echarts = echarts;

registerInputDatePicker(Alpine);

if (!window.horizon) window.horizon = {};
window.horizon.http = createHttpHelpers();

if (!window.moment) window.moment = moment;

window.horizonJobsPage = horizonJobsPage;
window.horizonJobDetail = horizonJobDetail;
window.horizonJobRowRetry = horizonJobRowRetry;
window.horizonAlertsList = horizonAlertsList;
window.horizonAlertDetail = horizonAlertDetail;
window.horizonMetricsPage = horizonMetricsPage;

window.horizonHubTheme = initTheme();

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('turbo:before-cache', function () {
    Alpine.destroyTree(document.body);
});

document.addEventListener('turbo:load', function () {
    queueMicrotask(function () {
        Alpine.initTree(document.body);
    });
    schedule(function () {
        formatQueueWaitElements();
    });
    mountToaster();
});

onDocumentReady(function () {
    mountToaster();
    window.horizonHubTheme.applyTheme();
    schedule(function () {
        formatQueueWaitElements();
    });
    initTurboStream();
});

document.addEventListener('turbo:before-stream-render', function (e) {
    var original = e.detail.render;
    e.detail.render = function (streamElement) {
        if (!streamElement || !streamElement.getAttribute) return;
        var outcome = renderTurboStreamWithGuards(streamElement, original);
        if (outcome === 'skipped') {
            return;
        }
        var syncRoot = getTurboStreamTargetElement(streamElement);
        schedule(function () {
            formatQueueWaitElements(syncRoot);
            if (outcome === 'rendered') {
                if (syncRoot && typeof window.horizonSyncResizableTablesUnderRoot === 'function') {
                    window.horizonSyncResizableTablesUnderRoot(syncRoot);
                } else if (typeof window.horizonInitResizableTables === 'function') {
                    window.horizonInitResizableTables();
                }
            }
            initJsonTrees();
        });
    };
});

window.addEventListener('apply-theme', function () {
    window.horizonHubTheme.applyTheme();
});

/**
 * Schedule a callback.
 * @param {function} callback
 * @returns {void}
 */
function schedule(callback) {
    setTimeout(callback, 0);
}

/**
 * Initialize the document.
 * @param {function} callback
 * @returns {void}
 */
function onDocumentReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}
