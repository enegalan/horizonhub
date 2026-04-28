import { renderJsonTree } from '../lib/json-tree';
import { parseFailedAtRange } from '../lib/parse';

/**
 * Initialize JSON trees on the job detail page.
 * @returns {void}
 */
export function initJsonTrees() {
    var rootEl = document.querySelector('[data-horizon-job-detail-root="1"]');
    if (!rootEl) return;
    var jobUuid = String(rootEl.getAttribute('data-horizon-job-uuid') || '').trim();
    rootEl.querySelectorAll('[data-json-tree]').forEach(function (target) {
        var treeName = target.getAttribute('data-json-tree');
        var storageKey = null;
        if (jobUuid && treeName) {
            storageKey = 'horizonhub:job:' + jobUuid + ':json-tree:' + treeName;
        }
        renderJsonTree(target, { storageKey: storageKey });
    });
}

/**
 * Horizon jobs page.
 * @param {object} config
 * @returns {object}
 */
export function horizonJobsPage(config) {
    return {
        /**
         * Show retry modal.
         * @type {boolean}
         */
        showRetryModal: false,
        /**
         * Keep modal mounted while close animation runs.
         * @type {boolean}
         */
        retryModalMounted: false,
        /**
         * Retrying.
         * @type {boolean}
         */
        retrying: false,
        /**
         * Failed jobs.
         * @type {object[]}
         */
        failedJobs: [],
        /**
         * Selected jobs for batch retry (id = Horizon job UUID).
         * @type {{ id: string, service_id: number }[]}
         */
        selectedFailedJobs: [],
        /**
         * Selecting all failed jobs to retry.
         * @type {boolean}
         */
        selectingAllFailed: false,
        /**
         * Retry page.
         * @type {number}
         */
        retryPage: 1,
        /**
         * Retry last page.
         * @type {number}
         */
        retryLastPage: 1,
        /**
         * Retry total.
         * @type {number}
         */
        retryTotal: 0,
        /**
         * Retry per page.
         * @type {number}
         */
        retryPerPage: config.jobsPerPage,
        /**
         * Incremented when the retry modal opens; remounts service multiselect.
         * @type {number}
         */
        retryModalSession: 0,
        /**
         * Retry filters.
         * @type {object}
         */
        retryFilters: {
            service_ids: [],
            search: '',
            failed_at_range: '',
        },
        /**
         * Open the retry modal.
         * @returns {void}
         */
        openRetryModal() {
            this.retryModalSession = (this.retryModalSession || 0) + 1;
            this.retryFilters.service_ids = [];
            this.retryFilters.search = '';
            this.retryFilters.failed_at_range = '';
            this.retryModalMounted = true;
            this.showRetryModal = false;
            requestAnimationFrame(() => {
                this.showRetryModal = true;
                window.requestAnimationFrame(function () {
                    var table = document.querySelector('table[data-resizable-table="horizon-retry-modal-failed-jobs"]');
                    if (table && typeof window.horizonSyncResizableTableLayout === 'function') {
                        window.horizonSyncResizableTableLayout(table);
                    }
                });
            });
            this.selectedFailedJobs = [];
            this.selectingAllFailed = false;
            this.retryPage = 1;
            this.loadFailedJobs();
        },
        /**
         * Apply retry-modal filters (resets to page 1) and reload failed jobs.
         * @returns {void}
         */
        applyRetryModalFilters() {
            this.retryPage = 1;
            this.loadFailedJobs();
        },
        /**
         * Close the retry modal.
         * @returns {void}
         */
        closeRetryModal() {
            this.showRetryModal = false;
            this.selectedFailedJobs = [];
            this.selectingAllFailed = false;
            window.setTimeout(() => {
                if (!this.showRetryModal) {
                    this.retryModalMounted = false;
                }
            }, 220);
        },
        /**
         * Load the failed jobs.
         * @returns {void}
         */
        /**
         * Query string for GET /jobs/failed (retry modal).
         * @param {{ selection?: string }} options
         * @returns {URLSearchParams}
         */
        retryModalFailedListQuery(options) {
            options = options || {};
            var params = new URLSearchParams();
            var serviceIds = this.retryFilters.service_ids;
            if (Array.isArray(serviceIds)) {
                serviceIds.forEach(function (id) {
                    if (id !== null && id !== '' && id !== undefined) {
                        params.append('service_ids[]', String(id));
                    }
                });
            }
            if (this.retryFilters.search) params.append('search', this.retryFilters.search);
            var rangeParts = parseFailedAtRange(this.retryFilters.failed_at_range);
            if (rangeParts.dateFrom) params.append('date_from', rangeParts.dateFrom);
            if (rangeParts.dateTo) params.append('date_to', rangeParts.dateTo);
            if (options.selection) {
                params.append('selection', options.selection);
            }
            if (options.selection !== 'all') {
                if (this.retryPage) params.append('page', this.retryPage);
                if (this.retryPerPage) params.append('per_page', this.retryPerPage);
            }
            return params;
        },
        loadFailedJobs() {
            var params = this.retryModalFailedListQuery();
            var url = config.failedListUrl + (params.toString() ? ('?' + params.toString()) : '');
            window.horizon.http.get(url).then((data) => {
                this.failedJobs = Array.isArray(data.data) ? data.data : [];
                if (data.meta) {
                    this.retryPage = typeof data.meta.current_page === 'number' ? data.meta.current_page : 1;
                    this.retryLastPage = typeof data.meta.last_page === 'number' ? data.meta.last_page : 1;
                    this.retryPerPage = typeof data.meta.per_page === 'number' ? data.meta.per_page : this.retryPerPage;
                    this.retryTotal = typeof data.meta.total === 'number' ? data.meta.total : this.failedJobs.length;
                } else {
                    this.retryLastPage = 1;
                    this.retryTotal = this.failedJobs.length;
                }
            }).catch(function (error) {
                console.error('Failed loading failed jobs', error);
            });
        },
        /**
         * Toggle the failed job.
         * @param {string} id
         * @returns {void}
         */
        toggleFailed(id, serviceId) {
            var i = this.selectedFailedJobs.findIndex(function (j) { return j.id === id; });
            if (i >= 0) {
                this.selectedFailedJobs.splice(i, 1);
            } else {
                this.selectedFailedJobs.push({
                    id: id,
                    service_id: typeof serviceId === 'number' ? serviceId : Number(serviceId),
                });
            }
        },
        /**
         * Select all failed jobs.
         * @returns {void}
         */
        selectAllFailed() {
            var self = this;
            if (this.selectingAllFailed) return;
            this.selectingAllFailed = true;
            var params = this.retryModalFailedListQuery({ selection: 'all' });
            var url = config.failedListUrl + (params.toString() ? ('?' + params.toString()) : '');
            window.horizon.http.get(url).then(function (data) {
                var jobs = Array.isArray(data.jobs) ? data.jobs : [];
                self.selectedFailedJobs = jobs
                    .map(function (j) {
                        return {
                            id: String(j.id || ''),
                            service_id: typeof j.service_id === 'number' ? j.service_id : Number(j.service_id),
                        };
                    })
                    .filter(function (j) {
                        return j.id !== '' && !Number.isNaN(j.service_id) && j.service_id > 0;
                    });
            }).catch(function (error) {
                console.error('Failed selecting all failed jobs', error);
            }).finally(function () {
                self.selectingAllFailed = false;
            });
        },
        /**
         * Clear the selection.
         * @returns {void}
         */
        clearSelection() {
            this.selectedFailedJobs = [];
        },
        /**
         * Set the retry page.
         * @param {number} page
         * @returns {void}
         */
        setRetryPage(page) {
            var p = typeof page === 'number' ? page : Number(page);
            if (!p || p < 1 || p > this.retryLastPage) return;
            this.retryPage = p;
            this.loadFailedJobs();
        },
        /**
         * Slider items for the retry modal pagination nav.
         * @returns {(number|string)[]}
         */
        retryPaginationPages() {
            return buildRetryPaginationSlider(this.retryPage, this.retryLastPage, 2);
        },
        /**
         * Same copy as x-pagination summary.
         * @returns {string}
         */
        retryPaginationSummaryLine() {
            var tot = this.retryTotal;
            var count = this.failedJobs.length;
            if (tot === 0) {
                return 'Showing 0 items';
            }
            if (count === 0) {
                return 'Showing ' + tot + ' items';
            }
            var fi = (this.retryPage - 1) * this.retryPerPage + 1;
            var li = fi + count - 1;
            return 'Showing ' + fi + '\u2013' + li + ' of ' + tot;
        },
        /**
         * Previous retry page.
         * @returns {void}
         */
        prevRetryPage() {
            this.setRetryPage(this.retryPage - 1);
        },
        /**
         * Next retry page.
         * @returns {void}
         */
        nextRetryPage() {
            this.setRetryPage(this.retryPage + 1);
        },
        /**
         * Retry selected jobs.
         * @returns {void}
         */
        retrySelected() {
            var self = this;
            if (!window.horizon || !window.horizon.http) return;
            if (this.selectedFailedJobs.length === 0) return;
            var jobs = this.selectedFailedJobs.map(function (j) {
                return { id: j.id, service_id: j.service_id };
            });
            var requestedCount = jobs.length;
            this.retrying = true;
            window.horizon.http.post(config.retryBatchUrl, { jobs: jobs }).then(function (data) {
                self.retrying = false;
                self.closeRetryModal();
                if (window.toast && window.toast.success) {
                    var n = typeof data.succeeded === 'number' ? data.succeeded : requestedCount;
                    var msg = 'Retry requested for ' + n + ' job(s).';
                    window.toast.success(msg);
                }
            }).catch(function () {
                self.retrying = false;
            });
        },
    };
}

/**
 * Single failed job row retry (actions column).
 * @param {object} config
 * @param {string} config.retryUrl
 * @returns {object}
 */
export function horizonJobRowRetry(config) {
    return {
        /**
         * Retrying.
         * @type {boolean}
         */
        retrying: false,
        /**
         * Retry the job.
         * @returns {void}
         */
        retry() {
            postSingleJobRetry(config.retryUrl, this, 'Retry requested.');
        },
    };
}

/**
 * Horizon job detail.
 * @param {object} config
 * @returns {object}
 */
export function horizonJobDetail(config) {
    return {
        /**
         * Retrying.
         * @type {boolean}
         */
        retrying: false,
        /**
         * Show all exception lines on the job detail page.
         * @type {boolean}
         */
        showAllExceptionLines: false,
        /**
         * Initialize the job detail.
         * @returns {void}
         */
        init() {
            this.restorePersistedUiState();
            initJsonTrees();
            var root = this.$el;
            if (!root || root.dataset.jobDetailRetryDelegated === '1') {
                return;
            }
            root.dataset.jobDetailRetryDelegated = '1';
            root.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-job-detail-retry]');
                if (!btn || !root.contains(btn)) {
                    return;
                }
                var data = window.Alpine && typeof window.Alpine.$data === 'function' ? window.Alpine.$data(root) : null;
                if (data && typeof data.retry === 'function') {
                    e.preventDefault();
                    data.retry();
                }
            });
        },
        /**
         * Build the localStorage key for exception toggle state.
         * @returns {string|null}
         */
        getExceptionStorageKey() {
            var rootEl = document.querySelector('[data-horizon-job-detail-root="1"]');

            var jobUuid = String(rootEl && rootEl.getAttribute('data-horizon-job-uuid') || '').trim();

            if (!jobUuid) return null;
            return 'horizonhub:job-detail:' + jobUuid + ':exceptions:show-all';
        },
        /**
         * Persist UI state (exceptions toggle).
         * @returns {void}
         */
        persistUiState() {
            if (typeof window === 'undefined' || !window.localStorage) return;
            var key = this.getExceptionStorageKey();
            if (!key) return;
            try {
                window.localStorage.setItem(key, this.showAllExceptionLines ? '1' : '0');
            } catch (e) {
            }
        },
        /**
         * Restore persisted UI state (exceptions toggle).
         * @returns {void}
         */
        restorePersistedUiState() {
            if (typeof window === 'undefined' || !window.localStorage) return;
            var key = this.getExceptionStorageKey();
            if (!key) return;
            try {
                var raw = window.localStorage.getItem(key);
                if (raw === null) return;
                this.showAllExceptionLines = raw === '1' || raw === 'true';
            } catch (e) {
            }
        },
        /**
         * Retry the job.
         * @returns {void}
         */
        retry() {
            if (!config.canRetry) return;
            postSingleJobRetry(config.retryUrl, this, 'Retry requested.');
        },
        /**
         * Toggle exception lines.
         * @returns {void}
         */
        toggleExceptionLines() {
            this.showAllExceptionLines = !this.showAllExceptionLines;
            this.persistUiState();
        },
    };
}

/**
 * POST retry URL, toast, and dispatch refresh (single job flows).
 * @param {string} retryUrl
 * @param {{ retrying: boolean }} component
 * @param {string} toastMessage
 * @returns {void}
 */
function postSingleJobRetry(retryUrl, component, toastMessage) {
    if (!window.horizon || !window.horizon.http) return;
    component.retrying = true;
    window.horizon.http.post(retryUrl, {}).then(function () {
        if (window.toast && window.toast.success) {
            window.toast.success(toastMessage);
        }
    }).catch(function () {
    }).finally(function () {
        component.retrying = false;
    });
}

/**
 * Page number window for pagination nav.
 * @param {number} current
 * @param {number} last
 * @param {number} onEachSide
 * @returns {(number|string)[]}
 */
function buildRetryPaginationSlider(current, last, onEachSide) {
    var w = onEachSide;
    var slider = [];
    var i;
    if (last <= (w * 2 + 3)) {
        for (i = 1; i <= last; i++) {
            slider.push(i);
        }
    } else if (current <= w + 2) {
        var end = Math.min(w * 2 + 2, last);
        for (i = 1; i <= end; i++) {
            slider.push(i);
        }
        slider.push('...');
        slider.push(last);
    } else if (current >= last - w - 1) {
        slider.push(1);
        slider.push('...');
        var start = Math.max(1, last - w * 2 - 1);
        for (i = start; i <= last; i++) {
            slider.push(i);
        }
    } else {
        slider.push(1);
        slider.push('...');
        for (i = current - w; i <= current + w; i++) {
            slider.push(i);
        }
        slider.push('...');
        slider.push(last);
    }
    return slider;
}
