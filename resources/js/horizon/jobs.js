import { formatDateTimeElements } from '../lib/datetime-format';
import { onHorizonHubRefresh, decodeHtmlEntities } from '../lib/dom';
import hljs from 'highlight.js/lib/core';
import jsonLanguage from 'highlight.js/lib/languages/json';

hljs.registerLanguage('json', jsonLanguage);

/**
 * Parse JSON sources that can arrive escaped/encoded.
 * @param {string} rawSource
 * @returns {unknown}
 */
function parseJsonSource(rawSource) {
    var candidate = decodeHtmlEntities(String(rawSource)).trim();
    var seen = new Set();

    for (;;) {
        if (seen.has(candidate)) {
            return candidate;
        }
        seen.add(candidate);

        try {
            var parsed = JSON.parse(candidate);

            if (typeof parsed !== 'string') {
                return parsed;
            }

            var nextCandidate = decodeHtmlEntities(parsed).trim();
            if (!nextCandidate) return '';

            var startsLikeJson =
                nextCandidate.startsWith('{') ||
                nextCandidate.startsWith('[') ||
                nextCandidate.startsWith('"');

            if (!startsLikeJson) {
                return parsed;
            }

            candidate = nextCandidate;
        } catch (error) {
            return candidate;
        }
    }
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
 * @param {string[]} pathSegments
 * @param {{ isCollapsed: function(string): boolean, setCollapsed: function(string, boolean): void }|null} state
 * @returns {HTMLElement}
 */
function buildJsonNode(key, value, pathSegments, state) {
    var wrapper = document.createElement('div');
    wrapper.className = 'horizon-json-node';

    var isArray = Array.isArray(value);
    var isObject = value !== null && typeof value === 'object' && !isArray;
    var isContainer = isArray || isObject;

    var line = document.createElement('div');
    line.className = 'horizon-json-line';
    wrapper.appendChild(line);

    var currentPathSegments = Array.isArray(pathSegments) ? pathSegments.slice() : [];
    if (key !== null) {
        currentPathSegments.push(String(key));
    }

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

    var pointer = '/' + currentPathSegments.map(function (segment) {
        return String(segment).replace(/~/g, '~0').replace(/\//g, '~1');
    }).join('/');

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
            childrenContainer.appendChild(buildJsonNode(String(index), childValue, currentPathSegments, state));
        });
    } else {
        children.forEach(function (entry) {
            childrenContainer.appendChild(buildJsonNode(entry[0], entry[1], currentPathSegments, state));
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
        var nextExpanded = !expanded;
        setExpanded(nextExpanded);
        if (state) state.setCollapsed(pointer, !nextExpanded);
    });
    closeToggle.addEventListener('click', function () {
        var expanded = closeToggle.getAttribute('aria-expanded') === 'true';
        var nextExpanded = !expanded;
        setExpanded(nextExpanded);
        if (state) state.setCollapsed(pointer, !nextExpanded);
    });

    if (state && state.isCollapsed(pointer)) {
        setExpanded(false);
    }

    return wrapper;
}

/**
 * Create a persisted JSON tree state store.
 * @param {string} storageKey
 * @returns {{ isCollapsed: function(string): boolean, setCollapsed: function(string, boolean): void }|null}
 */
function createJsonTreeStateStore(storageKey) {
    if (typeof window === 'undefined') return null;
    if (!storageKey) return null;

    var cache = new Set();
    try {
        var raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
        if (raw) {
            var parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                parsed.forEach(function (p) {
                    if (typeof p === 'string' && p) cache.add(p);
                });
            }
        }
    } catch (e) {
    }

    function persist() {
        try {
            if (!window.localStorage) return;
            window.localStorage.setItem(storageKey, JSON.stringify(Array.from(cache)));
        } catch (e) {
        }
    }

    return {
        isCollapsed: function (pointer) {
            return cache.has(pointer);
        },
        setCollapsed: function (pointer, collapsed) {
            if (!pointer) return;
            if (collapsed) cache.add(pointer);
            else cache.delete(pointer);
            persist();
        },
    };
}

/**
 * Render JSON tree inside a target element.
 * @param {HTMLElement} target
 * @param {{ storageKey?: string }=} options
 * @returns {void}
 */
function renderJsonTree(target, options) {
    if (!target || !target.getAttribute) return;

    var source = target.getAttribute('data-json-source');
    var parsed = null;
    if (typeof source === 'string' && source !== '') {
        parsed = parseJsonSource(source);
    }

    var state = null;
    if (options && typeof options.storageKey === 'string' && options.storageKey) {
        state = createJsonTreeStateStore(options.storageKey);
    }

    target.innerHTML = '';
    target.classList.add('horizon-json-tree');
    target.appendChild(buildJsonNode(null, parsed, [], state));
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
            this.persistUiState();
        },
        /**
         * Enhance detail blocks.
         * @param {Document|HTMLElement} root
         * @returns {void}
         */
        enhanceJobDetail(root) {
            if (!root || !root.querySelectorAll) return;
            var rootEl = root.querySelector ? root.querySelector('[data-horizon-job-detail-root="1"]') : null;
            var jobUuid = rootEl && rootEl.getAttribute ? String(rootEl.getAttribute('data-horizon-job-uuid') || '').trim() : '';
            var jsonTargets = root.querySelectorAll('[data-json-tree][data-json-source]');
            jsonTargets.forEach(function (target) {
                var treeName = target.getAttribute('data-json-tree');
                var storageKey = null;
                if (jobUuid && treeName) {
                    storageKey = 'horizonhub:job-detail:' + jobUuid + ':json-tree:' + treeName;
                }
                renderJsonTree(target, { storageKey: storageKey });
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
