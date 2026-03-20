import { formatDateTimeElements } from '../lib/datetime-format';

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
         * Retry filters.
         * @type {object}
         */
        retryFilters: {
            service_id: '',
            search: '',
            date_from: '',
            date_to: '',
        },
        /**
         * Initialize the jobs page.
         * @returns {void}
         */
        init() {
            var self = this;
            window.addEventListener('horizonhub-refresh', function (e) {
                if (self.retryModalMounted) return;
                if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
                self.refreshJobsTable(e.detail && e.detail.document);
            });
        },
        /**
         * Open the retry modal.
         * @returns {void}
         */
        openRetryModal() {
            this.retryModalMounted = true;
            this.showRetryModal = false;
            requestAnimationFrame(() => {
                this.showRetryModal = true;
            });
            this.selectedFailedIds = [];
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
            if (this.retryFilters.service_id) params.append('service_id', this.retryFilters.service_id);
            if (this.retryFilters.search) params.append('search', this.retryFilters.search);
            if (this.retryFilters.date_from) params.append('date_from', this.retryFilters.date_from);
            if (this.retryFilters.date_to) params.append('date_to', this.retryFilters.date_to);
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
            if (!page || page < 1 || page > this.retryLastPage) return;
            this.retryPage = page;
            this.loadFailedJobs();
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
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newTable = preloadedDoc.querySelector('[data-resizable-table="horizon-job-list"]');
            var currentTable = document.querySelector('[data-resizable-table="horizon-job-list"]');
            if (!newTable || !currentTable) return;
            var newTbody = newTable.querySelector('tbody');
            var currentTbody = currentTable.querySelector('tbody');
            if (newTbody && currentTbody) {
                currentTbody.replaceWith(newTbody);
                formatDateTimeElements(currentTable);
            }
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
        /**
         * Initialize the job detail.
         * @returns {void}
         */
        init() {
            var self = this;
            window.addEventListener('horizonhub-refresh', function (e) {
                if (self.retrying) return;
                if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
                self.refreshJobDetail(e.detail && e.detail.document);
            });
        },
        /**
         * Retry the job.
         * @returns {void}
         */
        retry() {
            if (!config.canRetry || !window.horizon || !window.horizon.http) return;
            this.retrying = true;
            var self = this;
            window.horizon.http.post(config.retryUrl, {}).then(function () {
                if (window.toast && window.toast.success) {
                    window.toast.success('Retry requested.');
                }
                if (typeof window !== 'undefined') {
                    window.dispatchEvent(new Event('horizonhub-refresh'));
                }
            }).catch(function () {
            }).finally(function () {
                self.retrying = false;
            });
        },
        /**
         * Refresh the job detail.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshJobDetail(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newRoot = preloadedDoc.querySelector('[data-horizon-job-detail-root="1"]');
            var currentRoot = document.querySelector('[data-horizon-job-detail-root="1"]');
            if (!newRoot || !currentRoot) return;
            currentRoot.replaceWith(newRoot);
            formatDateTimeElements(newRoot);
        },
    };
}
