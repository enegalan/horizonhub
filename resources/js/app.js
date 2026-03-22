import './bootstrap';
import './components/resizable-table';
import { horizonJobsPage, horizonJobDetail, horizonJobRowRetry } from './horizon/jobs';
import { horizonServiceDashboard, horizonServiceList } from './horizon/services';
import { horizonQueueList } from './horizon/queues';
import { horizonAlertsList, horizonAlertDetail } from './horizon/alerts';
import { horizonMetricsPage } from './horizon/metrics';
import { initRefreshStream } from './lib/sse-hot-reload';
import { createHttpHelpers } from './lib/http';
import { formatQueueWaitElements, observeQueueWaitElements } from './lib/queue-wait-format';
import { formatDateTimeElements } from './lib/datetime-format';
import { mountToaster } from './components/toaster';
import { hydrateMetricsChartsFromDom, hydrateAlertDetailChartsFromDom } from './charts/metrics-charts';
import { applyTheme } from './components/theme';
import { registerInputDatePicker } from './components/input-date-picker';
import { onDocumentReady, schedule } from './utils/init';
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
 * Hydrate the page.
 * @returns {void}
 */
function hydratePage() {
    schedule(function () {
        hydrateMetricsChartsFromDom();
        hydrateAlertDetailChartsFromDom();
        formatDateTimeElements();
        formatQueueWaitElements();
    });
}

/**
 * Initialize the app.
 * @returns {void}
 */
onDocumentReady(function () {
    mountToaster();
    applyTheme();
    window.dispatchEvent(new CustomEvent('apply-theme'));
    hydratePage();
    observeQueueWaitElements();
    initRefreshStream();
});

window.addEventListener('apply-theme', function () {
    applyTheme();
});
