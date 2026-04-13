import './bootstrap';
import './components/resizable-table';
import * as Turbo from '@hotwired/turbo';
import { horizonJobsPage, horizonJobDetail, horizonJobRowRetry, initJsonTrees } from './horizon/jobs';
import { horizonAlertsList, horizonAlertDetail } from './horizon/alerts';
import { horizonMetricsPage } from './horizon/metrics';
import { initTurboStream } from './lib/sse';
import { createHttpHelpers } from './lib/http';
import { formatDateTimeElements, formatQueueWaitElements } from './lib/datetime-format';
import { onDocumentReady, schedule } from './lib/init';
import { getTurboStreamTargetElement, renderTurboStreamWithGuards } from './lib/stream-guard';
import { mountToaster } from './components/toaster';
import { applyTheme } from './components/theme';
import { registerInputDatePicker } from './components/input-date-picker';
import Alpine from 'alpinejs';
import moment from 'moment';

window.Turbo = Turbo;

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
        formatDateTimeElements();
        formatQueueWaitElements();
    });
});

/**
 * Initialize the app.
 * @returns {void}
 */
onDocumentReady(function () {
    mountToaster();
    applyTheme();
    schedule(function () {
        formatDateTimeElements();
        formatQueueWaitElements();
    });
    initTurboStream();
});

document.addEventListener('turbo:before-stream-render', function (e) {
    var original = e.detail.render;
    e.detail.render = function (streamElement) {
        var outcome = renderTurboStreamWithGuards(streamElement, original);
        if (outcome === 'skipped') {
            return;
        }
        var syncRoot = getTurboStreamTargetElement(streamElement);
        schedule(function () {
            var formatRoot = syncRoot || document;
            formatDateTimeElements(formatRoot);
            formatQueueWaitElements(formatRoot);
            if (syncRoot && typeof window.horizonSyncResizableTablesUnderRoot === 'function') {
                window.horizonSyncResizableTablesUnderRoot(syncRoot);
            } else if (typeof window.horizonInitResizableTables === 'function') {
                window.horizonInitResizableTables();
            }
            initJsonTrees();
        });
    };
});

window.addEventListener('apply-theme', function () {
    applyTheme();
});
