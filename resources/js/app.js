import './bootstrap';
import './components/resizable-table';
import './components/form-drawer';
import { startAlpine } from './alpine';
import { renderJsonTrees } from './horizon/jobs';
import { renderAlertDetailCharts } from './horizon/alerts';
import { renderMetricsCharts } from './horizon/metrics';
import { initTurboStream } from './lib/sse';
import { formatDatetimeElements } from './lib/datetime-format';
import { getTurboStreamTargetElement, renderTurboStreamWithGuards } from './lib/stream-guard';
import { mountToaster } from './components/toaster';

startAlpine();

document.addEventListener('turbo:load', function () {
    setTimeout(function () {
        formatDatetimeElements();
    }, 0);
});

onDocumentReady(function () {
    initTurboStream();
    mountToaster();
});

document.addEventListener('turbo:before-stream-render', function (e) {
    var original = e.detail.render;
    e.detail.render = function (streamElement) {
        if (!streamElement || !streamElement.getAttribute || (typeof document !== 'undefined' && document.visibilityState !== 'visible')) return;
        var outcome = renderTurboStreamWithGuards(streamElement, original);
        var syncRoot = getTurboStreamTargetElement(streamElement);
        setTimeout(function () {
            formatDatetimeElements(syncRoot);
            if (outcome === 'rendered' && typeof window.horizonSyncResizableTablesUnderRoot === 'function') {
                window.horizonSyncResizableTablesUnderRoot(syncRoot);
            }
            renderJsonTrees();
            renderMetricsCharts();
            renderAlertDetailCharts();
        }, 0);
    };
});

window.addEventListener('apply-theme', function () {
    window.horizon.theme.apply();
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
