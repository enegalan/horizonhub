import { formatDateTimeElements } from '../lib/datetime-format';
import { onHorizonHubRefresh } from '../lib/dom';
import { renderJsonTree } from '../lib/json-tree';
import { parseFailedAtRange } from '../lib/parse';

/**
 * Swap a live DOM subtree with the same selector from a fetched document.
 * @param {string} selector
 * @param {Document} preloadedDoc
 * @param {{ afterReplace?: function(HTMLElement): void }=} options
 * @returns {HTMLElement|null}
 */
function replaceHorizonRootFromDoc(selector, preloadedDoc, options) {
    if (typeof window === 'undefined' || typeof document === 'undefined') return null;
    if (!preloadedDoc) return null;
    var newRoot = preloadedDoc.querySelector(selector);
    var currentRoot = document.querySelector(selector);
    if (!newRoot || !currentRoot) return null;
    currentRoot.replaceWith(newRoot);
    formatDateTimeElements(newRoot);
    if (typeof window !== 'undefined' && window.Alpine && typeof window.Alpine.initTree === 'function') {
        window.Alpine.initTree(newRoot);
    }
    if (options && typeof options.afterReplace === 'function') {
        options.afterReplace(newRoot);
    }
    return newRoot;
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
    var shouldRefresh = false;
    window.horizon.http.post(retryUrl, {}).then(function () {
        if (window.toast && window.toast.success) {
            window.toast.success(toastMessage);
        }
        shouldRefresh = true;
    }).catch(function () {
    }).finally(function () {
        component.retrying = false;
        if (shouldRefresh && typeof window !== 'undefined') {
            window.dispatchEvent(new Event('horizonhub-refresh'));
        }
    });
}

/**
 * Page number window for pagination nav (matches resources/views/components/pagination.blade.php).
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

/**
 * Resolve the job detail root. `Element#querySelector` does not match the element itself,
 * so when `root` is already the detail div (e.g. after SSE replace), we must return it.
 * @param {Document|HTMLElement|null} root
 * @returns {HTMLElement|null}
 */
function resolveHorizonJobDetailRoot(root) {
    if (!root) return null;
    if (root.nodeType === 1 && root.getAttribute && root.getAttribute('data-horizon-job-detail-root') === '1') {
        return root;
    }
    if (root.querySelector) {
        return root.querySelector('[data-horizon-job-detail-root="1"]');
    }
    return null;
}

/**
 * Render JSON trees on the job detail page (localStorage keys require job UUID).
 * @param {Document|HTMLElement} root
 * @returns {void}
 */
export function enhanceHorizonJobDetailJsonTrees(root) {
    if (!root || !root.querySelectorAll) return;
    var rootEl = resolveHorizonJobDetailRoot(root);
    if (!rootEl) return;
    var jobUuid = String(rootEl.getAttribute('data-horizon-job-uuid') || '').trim();
    var jsonTargets = rootEl.querySelectorAll('[data-json-tree]');
    jsonTargets.forEach(function (target) {
        var treeName = target.getAttribute('data-json-tree');
        var storageKey = null;
        if (jobUuid && treeName) {
            storageKey = 'horizonhub:job-detail:' + jobUuid + ':json-tree:' + treeName;
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
        /*
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
         * Selected failed IDs.
         * @type {string[]}
         */
        selectedFailedIds: [],
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
         * Initialize the jobs page.
         * @returns {void}
         */
        init() {
            var self = this;
            onHorizonHubRefresh(function (doc) {
                self.refreshJobsTable(doc);
            }, {
                shouldSkip: function () {
                    return self.retryModalMounted;
                },
            });
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
            this.selectedFailedIds = [];
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
            this.selectedFailedIds = [];
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
        loadFailedJobs() {
            if (!window.horizon || !window.horizon.http) {
                console.warn('[horizonJobsPage] window.horizon.http is not available');
                return;
            }
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
            if (this.retryPage) params.append('page', this.retryPage);
            if (this.retryPerPage) params.append('per_page', this.retryPerPage);
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
                // Do not clear selectedFailedIds on pagination so selection is kept across pages.
                window.requestAnimationFrame(function () {
                    var table = document.querySelector('table[data-resizable-table="horizon-retry-modal-failed-jobs"]');
                    if (table && typeof window.horizonSyncResizableTableLayout === 'function') {
                        window.horizonSyncResizableTableLayout(table);
                    }
                });
            }).catch(function (error) {
                console.error('[horizonJobsPage] failedList request error', error);
            });
        },
        /**
         * Toggle the failed job.
         * @param {string} id
         * @returns {void}
         */
        toggleFailed(id) {
            var i = this.selectedFailedIds.indexOf(id);
            if (i >= 0) this.selectedFailedIds.splice(i, 1);
            else this.selectedFailedIds.push(id);
        },
        /**
         * Select all failed jobs.
         * @returns {void}
         */
        selectAllFailed() {
            this.selectedFailedIds = this.failedJobs
                .map(function (j) { return j.uuid; });
        },
        /**
         * Clear the selection.
         * @returns {void}
         */
        clearSelection() {
            this.selectedFailedIds = [];
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
         * Slider items for the retry modal pagination nav (same logic as x-pagination).
         * @returns {(number|string)[]}
         */
        retryPaginationPages() {
            return buildRetryPaginationSlider(this.retryPage, this.retryLastPage, 2);
        },
        /**
         * Same copy as x-pagination summary (items on current page vs total).
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
            if (this.selectedFailedIds.length === 0) return;
            var jobs = this.selectedFailedIds.map(function (id) {
                var job = self.failedJobs.find(function (j) { return j.uuid === id; });
                return job && job.service_id != null ? { id: job.uuid, service_id: job.service_id } : null;
            }).filter(Boolean);
            if (jobs.length === 0) return;
            this.retrying = true;
            window.horizon.http.post(config.retryBatchUrl, { jobs: jobs }).then(function (data) {
                self.retrying = false;
                self.closeRetryModal();
                if (window.toast && window.toast.success) {
                    var msg = 'Retry requested for ' + (data.succeeded || self.selectedFailedIds.length) + ' job(s).';
                    window.toast.success(msg);
                }
            }).catch(function () {
                self.retrying = false;
            });
        },
        /**
         * Refresh the jobs table.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshJobsTable(preloadedDoc) {
            replaceHorizonRootFromDoc('[data-horizon-jobs-stack-root="1"]', preloadedDoc, {
                afterReplace: function () {
                    if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                        window.horizonInitResizableTables();
                    }
                },
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
        retrying: false,
        showAllExceptionLines: false,
        /**
         * Get the job UUID from the current detail root element.
         * @returns {string}
         */
        getJobUuid() {
            if (typeof document === 'undefined') return '';
            var root = document.querySelector('[data-horizon-job-detail-root="1"]');
            if (!root || !root.getAttribute) return '';
            return String(root.getAttribute('data-horizon-job-uuid') || '').trim();
        },
        /**
         * Build the localStorage key for exception toggle state.
         * @returns {string|null}
         */
        getExceptionStorageKey() {
            if (typeof window === 'undefined') return null;
            var jobUuid = this.getJobUuid();
            if (!jobUuid) return null;
            return 'horizonhub:job-detail:' + jobUuid + ':exceptions:show-all';
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
         * Initialize the job detail.
         * @returns {void}
         */
        init() {
            var self = this;
            this.restorePersistedUiState();
            this.enhanceJobDetail(document);
            onHorizonHubRefresh(function (doc) {
                self.refreshJobDetail(doc);
            }, {
                shouldSkip: function () {
                    return self.retrying;
                },
            });
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
        /**
         * Enhance detail blocks.
         * @param {Document|HTMLElement} root
         * @returns {void}
         */
        enhanceJobDetail(root) {
            enhanceHorizonJobDetailJsonTrees(root);
        },
        /**
         * Refresh the job detail.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshJobDetail(preloadedDoc) {
            var self = this;
            replaceHorizonRootFromDoc('[data-horizon-job-detail-root="1"]', preloadedDoc, {
                afterReplace: function (newRoot) {
                    self.enhanceJobDetail(newRoot);
                },
            });
        },
    };
}
