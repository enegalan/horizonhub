import { formatDateTimeElements } from './datetime-format';

export function horizonJobsPage(config) {
    return {
        showRetryModal: false,
        showCleanModal: false,
        retrying: false,
        cleaning: false,
        cleanStep: 1,
        failedJobs: [],
        selectedFailedIds: [],
        cleanCount: 0,
        retryPage: 1,
        retryLastPage: 1,
        retryTotal: 0,
        retryPerPage: config.jobsPerPage,
        retryFilters: {
            service_id: '',
            search: '',
            date_from: '',
            date_to: '',
        },
        cleanFilters: {
            service_id: '',
            status: '',
            job_type: '',
        },
        init() {
            var self = this;
            window.addEventListener('horizonhub-refresh', function () {
                if (self.showRetryModal || self.showCleanModal) return;
                if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
                self.refreshJobsTable();
            });

            // if (typeof window.__horizonInitialCleanCount === 'number' && this.cleanCount === 0) {
            //     this.cleanCount = window.__horizonInitialCleanCount;
            // }
        },
        openRetryModal() {
            this.showRetryModal = true;
            this.selectedFailedIds = [];
            this.retryPage = 1;
            this.loadFailedJobs();
        },
        closeRetryModal() {
            this.showRetryModal = false;
            this.selectedFailedIds = [];
        },
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
                this.selectedFailedIds = this.selectedFailedIds.filter(function (id) {
                    return data.data.some(function (j) { return j.id === id && j.has_service; });
                });
            }).catch(function (error) {
                console.error('[horizonJobsPage] failedList request error', error);
            });
        },
        toggleFailed(id) {
            var i = this.selectedFailedIds.indexOf(id);
            if (i >= 0) this.selectedFailedIds.splice(i, 1);
            else this.selectedFailedIds.push(id);
        },
        selectAllFailed() {
            this.selectedFailedIds = this.failedJobs
                .filter(function (j) { return j.has_service; })
                .map(function (j) { return j.id; });
        },
        clearSelection() {
            this.selectedFailedIds = [];
        },
        setRetryPage(page) {
            if (!page || page < 1 || page > this.retryLastPage) return;
            this.retryPage = page;
            this.loadFailedJobs();
        },
        prevRetryPage() {
            this.setRetryPage(this.retryPage - 1);
        },
        nextRetryPage() {
            this.setRetryPage(this.retryPage + 1);
        },
        retrySelected() {
            var self = this;
            if (!window.horizon || !window.horizon.http) return;
            if (this.selectedFailedIds.length === 0) return;
            this.retrying = true;
            window.horizon.http.post(config.retryBatchUrl, { ids: this.selectedFailedIds }).then(function (data) {
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
        openCleanModal() {
            this.showCleanModal = true;
            this.cleanStep = 1;
            this.updateCleanCount();
        },
        closeCleanModal() {
            this.showCleanModal = false;
            this.cleanStep = 1;
        },
        updateCleanCount() {
            if (!window.horizon || !window.horizon.http) return;
            var payload = {
                service_id: this.cleanFilters.service_id || null,
                status: this.cleanFilters.status || null,
                job_type: this.cleanFilters.job_type || null,
                preview: true,
            };
            window.horizon.http.post(config.cleanUrl, payload).then((data) => {
                if (typeof data.total_deleted === 'number') {
                    this.cleanCount = data.total_deleted;
                } else if (typeof data.deleted_jobs === 'number' || typeof data.deleted_failed_jobs === 'number') {
                    this.cleanCount = (data.deleted_jobs || 0) + (data.deleted_failed_jobs || 0);
                }
            }).catch(function () {
            });
        },
        confirmClean() {
            if (this.cleanCount === 0) return;
            this.cleanStep = 2;
        },
        runClean() {
            var self = this;
            if (!window.horizon || !window.horizon.http) return;
            this.cleaning = true;
            var payload = {
                service_id: this.cleanFilters.service_id || null,
                status: this.cleanFilters.status || null,
                job_type: this.cleanFilters.job_type || null,
            };
            window.horizon.http.post(config.cleanUrl, payload).then(function (data) {
                self.cleaning = false;
                self.closeCleanModal();
                if (window.toast && window.toast.success) {
                    var msg = (data.total_deleted || 0) + ' job(s) cleaned.';
                    window.toast.success(msg);
                }
                if (typeof window.location !== 'undefined') {
                    window.location.reload();
                }
            }).catch(function () {
                self.cleaning = false;
            });
        },
        refreshJobsTable() {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            var url = window.location.href;
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (response) {
                if (!response.ok) return null;
                return response.text();
            }).then(function (html) {
                if (!html) return;
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newTable = doc.querySelector('[data-resizable-table="horizon-job-list"]');
                var currentTable = document.querySelector('[data-resizable-table="horizon-job-list"]');
                if (!newTable || !currentTable) return;

                var newTbody = newTable.querySelector('tbody');
                var currentTbody = currentTable.querySelector('tbody');
                if (newTbody && currentTbody) {
                    currentTbody.replaceWith(newTbody);
                    formatDateTimeElements(currentTable);
                }
            }).catch(function () {
            });
        },
    };
}

export function horizonJobDetail(config) {
    return {
        retrying: false,
        init() {
            var self = this;
            window.addEventListener('horizonhub-refresh', function () {
                if (self.retrying) return;
                if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;
                self.refreshJobDetail();
            });
        },
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
        refreshJobDetail() {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            var url = window.location.href;
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (response) {
                if (!response.ok) return null;
                return response.text();
            }).then(function (html) {
                if (!html) return;
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newRoot = doc.querySelector('[data-horizon-job-detail-root="1"]');
                var currentRoot = document.querySelector('[data-horizon-job-detail-root="1"]');
                if (!newRoot || !currentRoot) return;

                currentRoot.replaceWith(newRoot);
                formatDateTimeElements(newRoot);
            }).catch(function () {
            });
        },
    };
}
