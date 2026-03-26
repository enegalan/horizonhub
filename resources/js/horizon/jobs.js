import { formatDateTimeElements } from '../lib/datetime-format';
import { onHorizonHubRefresh } from '../lib/dom';
import hljs from 'highlight.js/lib/core';
import jsonLanguage from 'highlight.js/lib/languages/json';

hljs.registerLanguage('json', jsonLanguage);

/**
 * Decode HTML entities from a string.
 * @param {string} value
 * @returns {string}
 */
function decodeHtmlEntities(value) {
    if (typeof document === 'undefined') return value;
    var textarea = document.createElement('textarea');
    textarea.innerHTML = value;

    return textarea.value;
}

/**
 * Parse JSON sources that can arrive escaped/encoded.
 * @param {string} rawSource
 * @returns {unknown}
 */
function parseJsonSource(rawSource) {
    var candidate = decodeHtmlEntities(String(rawSource)).trim();
    var depth = 0;

    while (depth < 4) {
        depth++;
        try {
            var parsed = JSON.parse(candidate);
            if (typeof parsed === 'string') {
                var nextCandidate = decodeHtmlEntities(parsed).trim();
                if (!nextCandidate) return '';
                var startsLikeJson = nextCandidate.startsWith('{') || nextCandidate.startsWith('[') || nextCandidate.startsWith('"');
                if (!startsLikeJson) return parsed;
                candidate = nextCandidate;
                continue;
            }

            return parsed;
        } catch (error) {
            return candidate;
        }
    }

    return candidate;
}

/**
 * Highlight JSON inline value.
 * @param {unknown} value
 * @returns {string}
 */
function highlightJsonValue(value) {
    var jsonValue;
    try {
        jsonValue = JSON.stringify(value);
    } catch (error) {
        jsonValue = JSON.stringify(String(value));
    }

    if (typeof jsonValue !== 'string') {
        jsonValue = 'null';
    }

    return hljs.highlight(jsonValue, { language: 'json' }).value;
}

/**
 * Build a JSON key span.
 * @param {string} key
 * @returns {HTMLElement}
 */
function buildJsonKey(key) {
    var keyEl = document.createElement('span');
    keyEl.className = 'horizon-json-key';
    keyEl.innerHTML = hljs.highlight(JSON.stringify(key), { language: 'json' }).value;

    return keyEl;
}

/**
 * Build a primitive JSON value span.
 * @param {unknown} value
 * @returns {HTMLElement}
 */
function buildJsonPrimitive(value) {
    var valueEl = document.createElement('span');
    valueEl.className = 'horizon-json-value';
    valueEl.innerHTML = highlightJsonValue(value);

    return valueEl;
}

/**
 * Build a JSON node recursively.
 * @param {string|null} key
 * @param {unknown} value
 * @returns {HTMLElement}
 */
function buildJsonNode(key, value) {
    var wrapper = document.createElement('div');
    wrapper.className = 'horizon-json-node';

    var isArray = Array.isArray(value);
    var isObject = value !== null && typeof value === 'object' && !isArray;
    var isContainer = isArray || isObject;

    var line = document.createElement('div');
    line.className = 'horizon-json-line';
    wrapper.appendChild(line);

    if (key !== null) {
        line.appendChild(buildJsonKey(key));
        var colon = document.createElement('span');
        colon.className = 'horizon-json-colon';
        colon.textContent = ': ';
        line.appendChild(colon);
    }

    if (!isContainer) {
        line.appendChild(buildJsonPrimitive(value));
        return wrapper;
    }

    var children = isArray ? value : Object.entries(value);
    var openBrace = isArray ? '[' : '{';
    var closeBrace = isArray ? ']' : '}';

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'horizon-json-toggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('no-ring', 'true');
    toggle.textContent = openBrace;
    line.appendChild(toggle);

    var childrenContainer = document.createElement('div');
    childrenContainer.className = 'horizon-json-children';

    if (isArray) {
        children.forEach(function (childValue, index) {
            childrenContainer.appendChild(buildJsonNode(String(index), childValue));
        });
    } else {
        children.forEach(function (entry) {
            childrenContainer.appendChild(buildJsonNode(entry[0], entry[1]));
        });
    }

    wrapper.appendChild(childrenContainer);

    var closeLine = document.createElement('div');
    closeLine.className = 'horizon-json-line horizon-json-close';
    var closeToggle = document.createElement('button');
    closeToggle.type = 'button';
    closeToggle.className = 'horizon-json-toggle';
    closeToggle.setAttribute('aria-expanded', 'true');
    closeToggle.setAttribute('no-ring', 'true');
    closeToggle.textContent = closeBrace;
    closeLine.appendChild(closeToggle);
    wrapper.appendChild(closeLine);

    function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        closeToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        childrenContainer.classList.toggle('hidden', !expanded);
        closeLine.classList.toggle('hidden', !expanded);
        toggle.textContent = expanded ? openBrace : (openBrace + '...' + closeBrace);
    }

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!expanded);
    });
    closeToggle.addEventListener('click', function () {
        var expanded = closeToggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!expanded);
    });

    return wrapper;
}

/**
 * Render JSON tree inside a target element.
 * @param {HTMLElement} target
 * @returns {void}
 */
function renderJsonTree(target) {
    if (!target || !target.getAttribute) return;

    var source = target.getAttribute('data-json-source');
    var parsed = null;
    if (typeof source === 'string' && source !== '') {
        parsed = parseJsonSource(source);
    }

    target.innerHTML = '';
    target.classList.add('horizon-json-tree');
    target.appendChild(buildJsonNode(null, parsed));
}

/**
 * Split retry modal range field into API params.
 * @param {string} rangeValue
 * @returns {{ dateFrom: string, dateTo: string }}
 */
function parseFailedAtRange(rangeValue) {
    var v = typeof rangeValue === 'string' ? rangeValue.trim() : '';
    if (!v) {
        return { dateFrom: '', dateTo: '' };
    }
    var parts = v.split(/\s+to\s+/i).map(function (s) {
        return s.trim();
    }).filter(Boolean);
    if (parts.length === 0) {
        return { dateFrom: '', dateTo: '' };
    }
    if (parts.length === 1) {
        return { dateFrom: parts[0], dateTo: '' };
    }

    return { dateFrom: parts[0], dateTo: parts[1] };
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
         * Retry filters.
         * @type {object}
         */
        retryFilters: {
            service_id: '',
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
            var newRoot = preloadedDoc.querySelector('[data-horizon-jobs-stack-root="1"]');
            var currentRoot = document.querySelector('[data-horizon-jobs-stack-root="1"]');
            if (!newRoot || !currentRoot) return;
            currentRoot.replaceWith(newRoot);
            formatDateTimeElements(newRoot);
            if (typeof window !== 'undefined' && window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(newRoot);
            }
            if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }
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
         * Request additional refreshes after retry.
         * @returns {void}
         */
        schedulePostRetryRefreshes() {
            if (typeof window === 'undefined') return;
            [1500, 4000, 8000, 15000].forEach(function (delayMs) {
                window.setTimeout(function () {
                    window.dispatchEvent(new Event('horizonhub-refresh'));
                }, delayMs);
            });
        },
        /**
         * Retry the job.
         * @returns {void}
         */
        retry() {
            if (!window.horizon || !window.horizon.http) return;
            this.retrying = true;
            var self = this;
            var shouldRefresh = false;
            window.horizon.http.post(config.retryUrl, {}).then(function () {
                if (window.toast && window.toast.success) {
                    window.toast.success('Retry requested.');
                }
                shouldRefresh = true;
            }).catch(function () {
            }).finally(function () {
                self.retrying = false;
                if (shouldRefresh && typeof window !== 'undefined') {
                    window.dispatchEvent(new Event('horizonhub-refresh'));
                    self.schedulePostRetryRefreshes();
                }
            });
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
         * Request additional refreshes after retry.
         * @returns {void}
         */
        schedulePostRetryRefreshes() {
            if (typeof window === 'undefined') return;
            [1500, 4000, 8000, 15000].forEach(function (delayMs) {
                window.setTimeout(function () {
                    window.dispatchEvent(new Event('horizonhub-refresh'));
                }, delayMs);
            });
        },
        /**
         * Initialize the job detail.
         * @returns {void}
         */
        init() {
            var self = this;
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
            if (!config.canRetry || !window.horizon || !window.horizon.http) return;
            this.retrying = true;
            var self = this;
            var shouldRefresh = false;
            window.horizon.http.post(config.retryUrl, {}).then(function () {
                if (window.toast && window.toast.success) {
                    window.toast.success('Retry requested.');
                }
                shouldRefresh = true;
            }).catch(function () {
            }).finally(function () {
                self.retrying = false;
                if (shouldRefresh && typeof window !== 'undefined') {
                    window.dispatchEvent(new Event('horizonhub-refresh'));
                    self.schedulePostRetryRefreshes();
                }
            });
        },
        /**
         * Toggle exception lines.
         * @returns {void}
         */
        toggleExceptionLines() {
            this.showAllExceptionLines = !this.showAllExceptionLines;
        },
        /**
         * Enhance detail blocks.
         * @param {Document|HTMLElement} root
         * @returns {void}
         */
        enhanceJobDetail(root) {
            if (!root || !root.querySelectorAll) return;
            var jsonTargets = root.querySelectorAll('[data-json-tree][data-json-source]');
            jsonTargets.forEach(function (target) {
                renderJsonTree(target);
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
            this.enhanceJobDetail(newRoot);
            if (typeof window !== 'undefined' && window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(newRoot);
            }
        },
    };
}
