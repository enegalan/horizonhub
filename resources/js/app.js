import './bootstrap';
import './components/resizable-table';
import { horizonJobsPage, horizonJobDetail, horizonJobRowRetry } from './horizon/jobs';
import { horizonServiceDashboard, horizonServiceList } from './horizon/services';
import { horizonQueueList } from './horizon/queues';
import { horizonAlertsList, horizonAlertDetail } from './horizon/alerts';
import { horizonMetricsPage } from './horizon/metrics';
import { initRefreshStream } from './lib/sse';
import { createHttpHelpers } from './lib/http';
import { formatDateTimeElements, formatQueueWaitElements, observeQueueWaitElements } from './lib/datetime-format';
import { mountToaster } from './components/toaster';
import { applyTheme } from './components/theme';
import { registerInputDatePicker } from './components/input-date-picker';
import { onDocumentReady, schedule } from './lib/init';
import Alpine from 'alpinejs';
import moment from 'moment';

registerInputDatePicker(Alpine);

if (!window.horizon) window.horizon = {};
window.horizon.http = createHttpHelpers();

if (!window.moment) window.moment = moment;

window.horizonJobsPage = horizonJobsPage;
window.horizonJobDetail = horizonJobDetail;
window.horizonJobRowRetry = horizonJobRowRetry;
window.horizonServiceDashboard = horizonServiceDashboard;
window.horizonServiceList = horizonServiceList;
window.horizonQueueList = horizonQueueList;
window.horizonAlertsList = horizonAlertsList;
window.horizonAlertDetail = horizonAlertDetail;
window.horizonMetricsPage = horizonMetricsPage;

window.Alpine = Alpine;
Alpine.start();

/**
 * Initialize the app.
 * @returns {void}
 */
onDocumentReady(function () {
    mountToaster();
    applyTheme();
    // Initial hydration to initially show metrics charts and format elements
    schedule(function () {
        formatDateTimeElements();
        formatQueueWaitElements();
    });
    observeQueueWaitElements();
    initRefreshStream();
});

window.addEventListener('apply-theme', function () {
    applyTheme();
});
