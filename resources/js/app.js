import './bootstrap';
import './components/resizable-table';
import './components/form-drawer';
import { horizonJobsPage, horizonJobDetail, horizonJobRowRetry, initJsonTrees } from './horizon/jobs';
import { horizonAlertsList, horizonAlertDetail, renderAlertDetailCharts } from './horizon/alerts';
import { horizonServiceForm, horizonServicesList } from './horizon/services';
import { horizonMetricsPage, renderMetricsCharts } from './horizon/metrics';
import { initTurboStream } from './lib/sse';
import { formatDatetimeElements } from './lib/datetime-format';
import { getTurboStreamTargetElement, renderTurboStreamWithGuards } from './lib/stream-guard';
import { deleteConfirm } from './components/delete-confirm';
import { mountToaster } from './components/toaster';
import { registerInputDatePicker } from './components/input-date-picker';
import { initTheme } from './components/theme';
import Alpine from 'alpinejs';

registerInputDatePicker(Alpine);

window.horizonJobsPage = horizonJobsPage;
window.horizonJobDetail = horizonJobDetail;
window.horizonJobRowRetry = horizonJobRowRetry;
window.horizonAlertsList = horizonAlertsList;
window.horizonAlertDetail = horizonAlertDetail;
window.deleteConfirm = deleteConfirm;
window.horizonServiceForm = horizonServiceForm;
window.horizonServicesList = horizonServicesList;
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
    setTimeout(function () {
        formatDatetimeElements();
    }, 0);
    mountToaster();
});

onDocumentReady(function () {
    mountToaster();
    setTimeout(function () {
        formatDatetimeElements();
    }, 0);
    initTurboStream();
});

document.addEventListener('turbo:before-stream-render', function (e) {
    var original = e.detail.render;
    e.detail.render = function (streamElement) {
        if (!streamElement || !streamElement.getAttribute || (typeof document !== 'undefined' && document.visibilityState !== 'visible')) return;
        var outcome = renderTurboStreamWithGuards(streamElement, original);
        var syncRoot = getTurboStreamTargetElement(streamElement);
        setTimeout(function () {
            formatDatetimeElements(syncRoot);
            if (outcome === 'rendered') {
                if (syncRoot && typeof window.horizonSyncResizableTablesUnderRoot === 'function') {
                    window.horizonSyncResizableTablesUnderRoot(syncRoot);
                } else if (typeof window.horizonInitResizableTables === 'function') {
                    window.horizonInitResizableTables();
                }
            }
            initJsonTrees();
            renderMetricsCharts();
            renderAlertDetailCharts();
        }, 0);
    };
});

window.addEventListener('apply-theme', function () {
    window.horizonHubTheme.applyTheme();
});

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
