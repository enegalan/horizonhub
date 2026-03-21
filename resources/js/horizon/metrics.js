import { processMetricsChartQueue } from "../charts/metrics-charts";
import { formatQueueWaitElements } from "../lib/queue-wait-format";

/**
 * Base URL for service detail pages (no trailing slash), set from metrics page config.
 * @type {string}
 */
var metricsServiceShowBaseUrl = "";

/**
 * Fill a workload/supervisors table cell with the service name (linked when possible).
 * @param {HTMLTableCellElement} td
 * @param {{ service?: string, service_id?: number }} row
 * @returns {void}
 */
function fillMetricsServiceNameCell(td, row) {
    td.className = "px-4 py-2.5 text-sm text-muted-foreground break-all";
    td.setAttribute("data-column-id", "service");
    var name = row.service || "";
    var base = metricsServiceShowBaseUrl.replace(/\/$/, "");
    var id = row.service_id;
    if (base && id !== null && id !== undefined && String(id) !== "" && Number(id) > 0) {
        var a = document.createElement("a");
        a.className = "link";
        a.href = base + "/" + encodeURIComponent(String(id));
        a.textContent = name;
        td.appendChild(a);
    } else {
        td.textContent = name;
    }
}

/**
 * All loader IDs.
 * @type {string[]}
 */
var ALL_LOADER_IDS = [
    'metrics-loader-jobs-minute',
    'metrics-loader-jobs-hour',
    'metrics-loader-failed-seven',
    'metrics-loader-failure-rate',
    'metrics-loader-jobs-volume-chart',
    'metrics-loader-failure-rate-chart',
    'metrics-loader-runtime-chart',
    'metrics-loader-service-chart',
];

/**
 * Loader IDs for KPI summary cards only (not chart canvases).
 * @type {string[]}
 */
var SUMMARY_KPI_LOADER_IDS = [
    'metrics-loader-jobs-minute',
    'metrics-loader-jobs-hour',
    'metrics-loader-failed-seven',
];

/**
 * Append service_id[] query params for metrics API URLs.
 * @param {string} base
 * @param {string[]} serviceIds
 * @returns {string}
 */
function appendServiceIdsToUrl(base, serviceIds) {
    if (!base) return base;
    var ids = Array.isArray(serviceIds) ? serviceIds.filter(function (x) { return x !== null && x !== undefined && String(x) !== ''; }) : [];
    if (ids.length === 0) return base;
    var sep = base.indexOf('?') === -1 ? '?' : '&';
    var out = base;
    for (var i = 0; i < ids.length; i++) {
        out += sep + 'service_id[]=' + encodeURIComponent(String(ids[i]));
        sep = '&';
    }
    return out;
}

/**
 * Remove service_id / service_id[] from a URL object search params.
 * @param {URL} url
 * @returns {void}
 */
function deleteServiceIdSearchParams(url) {
    var toRemove = [];
    url.searchParams.forEach(function (_, k) {
        if (k === 'service_id' || k === 'service_id[]') {
            toRemove.push(k);
        }
    });
    toRemove.forEach(function (k) {
        url.searchParams.delete(k);
    });
}

/**
 * Hide the loader.
 * @param {string} id
 * @returns {void}
 */
function hideLoader(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

/**
 * Show the loader.
 * @param {string} id
 * @returns {void}
 */
function showLoader(id) {
    var el = document.getElementById(id);
    if (!el) return;
    if (id === 'metrics-loader-jobs-volume-chart' || id === 'metrics-loader-failure-rate-chart' || id === 'metrics-loader-runtime-chart') {
        el.style.display = 'flex';
    } else {
        el.style.display = '';
    }
}

/**
 * Format the number.
 * @param {number} n
 * @returns {string}
 */
function formatNum(n) {
    return typeof n === 'number' ? n.toLocaleString() : '—';
}

/**
 * Render the chart.
 * @param {object} data
 * @returns {void}
 */
function renderChart(data) {
    if (!data) return;
    window.__metricsChartQueue = window.__metricsChartQueue || [];
    window.__metricsChartQueue.push(data);
    processMetricsChartQueue();
}

/**
 * Set the summary placeholders.
 * @returns {void}
 */
function setSummaryPlaceholders() {
    var v = document.getElementById('metrics-value-jobs-minute');
    if (v) v.textContent = '—';
    v = document.getElementById('metrics-value-jobs-hour');
    if (v) v.textContent = '—';
    v = document.getElementById('metrics-value-failed-seven');
    if (v) v.textContent = '—';
    v = document.getElementById('metrics-value-failure-rate');
    if (v) v.textContent = '—';
}

/**
 * Clear the workload table.
 * @returns {void}
 */
function clearWorkloadTable() {
    var body = document.getElementById('metrics-workload-body');
    var empty = document.getElementById('metrics-workload-empty');
    var summary = document.getElementById('metrics-workload-summary');
    if (!body) return;
    while (body.firstChild) {
        body.removeChild(body.firstChild);
    }
    if (empty) {
        body.appendChild(empty);
        empty.style.display = '';
    }
    if (summary) {
        summary.textContent = '';
    }
}

/**
 * Clear the supervisors table.
 * @returns {void}
 */
function clearSupervisorsTable() {
    var body = document.getElementById('metrics-supervisors-body');
    var empty = document.getElementById('metrics-supervisors-empty');
    var summary = document.getElementById('metrics-supervisors-summary');
    if (!body) return;
    while (body.firstChild) {
        body.removeChild(body.firstChild);
    }
    if (empty) {
        body.appendChild(empty);
        empty.style.display = '';
    }
    if (summary) {
        summary.textContent = '';
    }
}

/**
 * Render the workload rows.
 * @param {object[]} rows
 * @returns {void}
 */
function renderWorkloadRows(rows) {
    var body = document.getElementById('metrics-workload-body');
    var empty = document.getElementById('metrics-workload-empty');
    var summary = document.getElementById('metrics-workload-summary');
    if (!body) return;

    while (body.firstChild) {
        body.removeChild(body.firstChild);
    }
    if (empty) body.appendChild(empty);

    if (!rows || !rows.length) {
        if (empty) empty.style.display = '';
        if (summary) summary.textContent = '';
        return;
    }

    if (empty) empty.style.display = 'none';

    var totalQueues = rows.length;
    var totalJobs = 0;

    rows.forEach(function (row) {
        totalJobs += row.jobs || 0;
        var tr = document.createElement('tr');
        tr.className = 'transition-colors hover:bg-muted/30';

        var tdService = document.createElement('td');
        fillMetricsServiceNameCell(tdService, row);
        tr.appendChild(tdService);

        var tdQueue = document.createElement('td');
        tdQueue.className = 'px-4 py-2.5 font-mono text-xs text-muted-foreground break-all';
        tdQueue.setAttribute('data-column-id', 'queue');
        tdQueue.textContent = row.queue || '';
        tr.appendChild(tdQueue);

        var tdJobs = document.createElement('td');
        tdJobs.className = 'px-4 py-2.5 text-sm text-muted-foreground';
        tdJobs.setAttribute('data-column-id', 'jobs');
        tdJobs.textContent = formatNum(row.jobs || 0);
        tr.appendChild(tdJobs);

        var tdProcesses = document.createElement('td');
        tdProcesses.className = 'px-4 py-2.5 text-sm text-muted-foreground';
        tdProcesses.setAttribute('data-column-id', 'processes');
        tdProcesses.textContent = row.processes !== null && row.processes !== undefined ? formatNum(row.processes) : '–';
        tr.appendChild(tdProcesses);

        var tdWait = document.createElement('td');
        tdWait.className = 'px-4 py-2.5 text-sm text-muted-foreground';
        tdWait.setAttribute('data-column-id', 'wait');
        if (row.wait !== null && row.wait !== undefined) {
            var span = document.createElement('span');
            span.setAttribute('data-wait-seconds', String(row.wait));
            span.textContent = row.wait.toFixed(2) + ' s';
            tdWait.appendChild(span);
        } else {
            tdWait.textContent = '–';
        }
        tr.appendChild(tdWait);

        body.appendChild(tr);
    });

    if (summary) summary.textContent = totalQueues + ' queue(s), ' + formatNum(totalJobs) + ' job(s) total';
    formatQueueWaitElements(body);
}

/**
 * Render the supervisors rows.
 * @param {object[]} rows
 * @returns {void}
 */
function renderSupervisorsRows(rows) {
    var body = document.getElementById('metrics-supervisors-body');
    var empty = document.getElementById('metrics-supervisors-empty');
    var summary = document.getElementById('metrics-supervisors-summary');
    if (!body) return;

    while (body.firstChild) {
        body.removeChild(body.firstChild);
    }
    if (empty) body.appendChild(empty);

    if (!rows || !rows.length) {
        if (empty) empty.style.display = '';
        if (summary) summary.textContent = '';
        return;
    }

    if (empty) empty.style.display = 'none';

    var total = rows.length;
    var online = 0;

    rows.forEach(function (row) {
        if (row.status === 'online') online++;

        var tr = document.createElement('tr');
        tr.className = 'transition-colors hover:bg-muted/30';

        var tdService = document.createElement('td');
        fillMetricsServiceNameCell(tdService, row);
        tr.appendChild(tdService);

        var tdName = document.createElement('td');
        tdName.className = 'px-4 py-2.5 font-mono text-xs text-muted-foreground break-all';
        tdName.setAttribute('data-column-id', 'supervisor');
        tdName.textContent = row.name || '';
        tr.appendChild(tdName);

        var tdJobs = document.createElement('td');
        tdJobs.className = 'px-4 py-2.5 text-sm text-muted-foreground text-right';
        tdJobs.setAttribute('data-column-id', 'jobs');
        tdJobs.textContent = typeof row.jobs === 'number' ? formatNum(row.jobs) : '–';
        tr.appendChild(tdJobs);

        var tdProcesses = document.createElement('td');
        tdProcesses.className = 'px-4 py-2.5 text-sm text-muted-foreground text-right';
        tdProcesses.setAttribute('data-column-id', 'processes');
        tdProcesses.textContent = typeof row.processes === 'number' ? formatNum(row.processes) : '–';
        tr.appendChild(tdProcesses);

        var tdStatus = document.createElement('td');
        tdStatus.className = 'px-4 py-2.5 text-xs';
        tdStatus.setAttribute('data-column-id', 'status');
        var badge = document.createElement('span');
        badge.className = row.status === 'online' ? 'badge-success' : 'badge-warning';
        badge.textContent = row.status === 'online' ? 'Online' : 'Stale';
        tdStatus.appendChild(badge);
        tr.appendChild(tdStatus);

        body.appendChild(tr);
    });

    if (summary) summary.textContent = total + ' supervisor(s), ' + online + ' online';
    if (window.formatDateTimeElements) window.formatDateTimeElements(body);
}

/**
 * Apply the metrics payload.
 * @param {object} payload
 * @returns {void}
 */
function applyMetricsPayload(payload) {
    if (!payload || payload.error) return;

    var s = payload.summary;
    if (s) {
        SUMMARY_KPI_LOADER_IDS.forEach(hideLoader);
        hideLoader('metrics-loader-failure-rate');
        var v = document.getElementById('metrics-value-jobs-minute');
        if (v) v.textContent = formatNum(s.jobsPastMinute);
        v = document.getElementById('metrics-value-jobs-hour');
        if (v) v.textContent = formatNum(s.jobsPastHour);
        v = document.getElementById('metrics-value-failed-seven');
        if (v) v.textContent = formatNum(s.failedPastSevenDays);
        v = document.getElementById('metrics-value-failure-rate');
        if (v && s.failureRate24h) {
            var r = s.failureRate24h;
            v.innerHTML = r.rate + '% <span class="text-xs font-normal text-muted-foreground">(' + r.failed + ' failed / ' + r.processed + ' processed)</span>';
        }
    }
    if (payload.jobRuntimesLast24h) {
        hideLoader('metrics-loader-runtime-chart');
        renderChart({ jobRuntimesLast24h: payload.jobRuntimesLast24h });
    }
    if (payload.failureRateOverTime) {
        hideLoader('metrics-loader-failure-rate-chart');
        renderChart({ failureRateOverTime: payload.failureRateOverTime });
    }
    if (payload.jobsVolumeLast24h) {
        hideLoader('metrics-loader-jobs-volume-chart');
        renderChart({ jobsVolumeLast24h: payload.jobsVolumeLast24h });
    }
    var rows = payload.workload;
    if (Array.isArray(rows)) {
        renderWorkloadRows(rows);
        hideLoader('metrics-loader-service-chart');
        var waits = {};
        rows.forEach(function (row) {
            if (!row || typeof row.queue !== 'string') return;
            if (row.wait === null || row.wait === undefined) return;
            var q = row.queue;
            var w = Number(row.wait);
            if (!isFinite(w)) return;
            if (waits[q] === undefined || w > waits[q]) waits[q] = w;
        });
        var names = Object.keys(waits);
        if (names.length > 0) {
            names.sort(function (a, b) { return waits[b] - waits[a]; });
            names = names.slice(0, 12);
            renderChart({ waitByQueue: { queues: names, wait: names.map(function (name) { return waits[name]; }) } });
        }
    }
    if (Array.isArray(payload.supervisors)) {
        renderSupervisorsRows(payload.supervisors);
    }
}

/**
 * Check if the hot reload is enabled.
 * @returns {boolean}
 */
function isHotReloadEnabled() {
    if (typeof window === 'undefined') return false;
    if (window.Alpine && window.Alpine.store && window.Alpine.store('hotReload')) {
        return !!window.Alpine.store('hotReload').enabled;
    }
    return localStorage.getItem('horizonhub_hotreload') !== 'false';
}

/**
 * Metrics stream backoff initial milliseconds.
 * @type {number}
 */
var METRICS_STREAM_BACKOFF_INITIAL_MS = 1000;
/**
 * Metrics stream backoff maximum milliseconds.
 * @type {number}
 */
var METRICS_STREAM_BACKOFF_MAX_MS = 30000;
/**
 * Metrics stream backoff multiplier.
 * @type {number}
 */
var METRICS_STREAM_BACKOFF_MULTIPLIER = 2;

/**
 * Start the metrics stream.
 * @param {string} streamUrl
 * @param {function(): string[]} getServiceIds
 * @param {function} onPayload
 * @param {string[]|null} initialServiceIds Ids for first connection (avoids DOM timing issues).
 * @returns {object}
 */
function startMetricsStream(streamUrl, getServiceIds, onPayload, initialServiceIds) {
    var eventSource = null;
    var reconnectTimeout = null;
    var backoffMs = METRICS_STREAM_BACKOFF_INITIAL_MS;
    var isFirstConnect = true;
    var connectionToken = 0;
    var replaced = false;

    /**
     * Close the stream.
     * @returns {void}
     */
    function closeStream() {
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
            reconnectTimeout = null;
        }
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
    }

    /**
     * Connect to the stream.
     * @returns {void}
     */
    function connect() {
        if (replaced || !streamUrl || !isHotReloadEnabled()) return;
        closeStream();
        connectionToken += 1;
        var thisToken = connectionToken;
        var ids = isFirstConnect && initialServiceIds && initialServiceIds.length
            ? initialServiceIds.map(String)
            : (typeof getServiceIds === 'function' ? getServiceIds() : []);
        if (isFirstConnect) isFirstConnect = false;
        var url = appendServiceIdsToUrl(streamUrl, ids);
        eventSource = new EventSource(url);

        eventSource.addEventListener('metrics', function (e) {
            if (thisToken !== connectionToken) return;
            backoffMs = METRICS_STREAM_BACKOFF_INITIAL_MS;
            if (typeof document === 'undefined' || document.visibilityState !== 'visible') return;
            try {
                var data = JSON.parse(e.data);
                if (typeof onPayload !== 'function') return;
                if (typeof requestAnimationFrame !== 'undefined') {
                    requestAnimationFrame(function () { if (thisToken !== connectionToken) return; onPayload(data); });
                } else {
                    onPayload(data);
                }
            } catch (err) {}
        });

        eventSource.onerror = function () {
            closeStream();
            reconnectTimeout = setTimeout(function () {
                connect();
                if (backoffMs < METRICS_STREAM_BACKOFF_MAX_MS) backoffMs *= METRICS_STREAM_BACKOFF_MULTIPLIER;
            }, backoffMs);
        };

        eventSource.onopen = function () {
            backoffMs = METRICS_STREAM_BACKOFF_INITIAL_MS;
        };
    }

    window.addEventListener('horizonhub-hotreload-changed', function (e) {
        if (e.detail && e.detail.enabled === true) {
            connect();
        } else {
            closeStream();
        }
    });

    var visibilityReconnectTimeout = null;
    function scheduleReconnectOnVisible() {
        if (visibilityReconnectTimeout) return;
        visibilityReconnectTimeout = setTimeout(function () {
            visibilityReconnectTimeout = null;
            if (typeof document !== 'undefined' && document.visibilityState === 'visible' && isHotReloadEnabled()) {
                connect();
            }
        }, 200);
    }
    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                closeStream();
                if (visibilityReconnectTimeout) {
                    clearTimeout(visibilityReconnectTimeout);
                    visibilityReconnectTimeout = null;
                }
            } else if (document.visibilityState === 'visible') {
                scheduleReconnectOnVisible();
            }
        });
    }

    if (isHotReloadEnabled()) connect();

    return {
        /**
         * Reconnect the metrics stream.
         * @returns {void}
         */
        reconnect: function () {
            closeStream();
            if (isHotReloadEnabled()) connect();
        },
        /**
         * Close the metrics stream (e.g. when replaced by a new instance). Prevents further reconnects.
         * @returns {void}
         */
        close: function () {
            replaced = true;
            closeStream();
        },
    };
}

/**
 * Load all metrics.
 * @param {object} baseUrls
 * @param {string[]} serviceIds
 * @returns {void}
 */
function loadAllMetrics(baseUrls, serviceIds) {
    ALL_LOADER_IDS.forEach(showLoader);
    setSummaryPlaceholders();
    clearWorkloadTable();
    clearSupervisorsTable();

    var urls = {
        summary: appendServiceIdsToUrl(baseUrls.summary, serviceIds),
        jobRuntimesLast24h: appendServiceIdsToUrl(baseUrls.jobRuntimesLast24h, serviceIds),
        failureRate: appendServiceIdsToUrl(baseUrls.failureRate, serviceIds),
        jobsVolumeLast24h: appendServiceIdsToUrl(baseUrls.jobsVolumeLast24h, serviceIds),
        supervisors: appendServiceIdsToUrl(baseUrls.supervisors, serviceIds),
        workload: appendServiceIdsToUrl(baseUrls.workload, serviceIds),
    };

    /**
     * Fetch the JSON from the URL.
     * @param {string} url
     * @returns {Promise<object|null>}
     */
    async function fetchJson(url) {
        try {
            var res = await fetch(url);
            return res.ok ? res.json() : null;
        } catch (_) {
            return null;
        }
    }

    Promise.all([
        fetchJson(urls.summary),
        fetchJson(urls.jobRuntimesLast24h),
        fetchJson(urls.failureRate),
        fetchJson(urls.jobsVolumeLast24h),
        fetchJson(urls.workload),
        fetchJson(urls.supervisors),
    ]).then(function (results) {
        var summary = results[0] && !results[0].error ? results[0] : null;
        var jobRuntimesLast24h = results[1] && !results[1].error ? results[1] : null;
        var failureRate = results[2] && !results[2].error ? results[2] : null;
        var jobsVolumeLast24h = results[3] && !results[3].error ? results[3] : null;
        var workloadData = results[4];
        var supervisorsData = results[5];
        applyMetricsPayload({
            summary: summary,
            jobRuntimesLast24h: jobRuntimesLast24h,
            failureRateOverTime: failureRate,
            jobsVolumeLast24h: jobsVolumeLast24h,
            workload: workloadData && workloadData.workload ? workloadData.workload : [],
            supervisors: supervisorsData && supervisorsData.supervisors ? supervisorsData.supervisors : [],
        });
        ALL_LOADER_IDS.forEach(hideLoader);
    }).catch(function () {
        ALL_LOADER_IDS.forEach(hideLoader);
    });
}

/**
 * Read selected service ids from the metrics multiselect (hidden inputs).
 * @param {HTMLElement|null} filterEl
 * @returns {string[]}
 */
function getMetricsServiceIdsFromDom(filterEl) {
    if (!filterEl) return [];
    var inputs = filterEl.querySelectorAll('input[type="hidden"][name="service_id[]"]');
    var out = [];
    inputs.forEach(function (inp) {
        if (inp && inp.value) out.push(String(inp.value));
    });
    out.sort();
    return out;
}

/**
 * Alpine component for the metrics page. Same pattern as horizonQueueList / horizonServiceDashboard.
 * @param {{ baseUrls: object, initialServiceIds?: string[] }} config
 * @returns {object}
 */
export function horizonMetricsPage(config) {
    var baseUrls = config && config.baseUrls ? config.baseUrls : {};
    var initialServiceIdsFromServer = config && Array.isArray(config.initialServiceIds)
        ? config.initialServiceIds.map(String).filter(Boolean)
        : [];
    var streamApi = null;

    return {
        /**
         * Initialize the metrics page.
         * @returns {void}
         */
        init: function () {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;

            metricsServiceShowBaseUrl = config && config.serviceShowBaseUrl ? String(config.serviceShowBaseUrl) : '';

            var filterEl = document.getElementById('metrics-service-filter');
            if (filterEl) {
                filterEl.addEventListener('change', function (e) {
                    var ids = (e.detail && Array.isArray(e.detail.values))
                        ? e.detail.values.map(String).filter(Boolean).sort()
                        : getMetricsServiceIdsFromDom(filterEl);
                    var url = new URL(window.location.href);
                    deleteServiceIdSearchParams(url);
                    ids.forEach(function (id) {
                        url.searchParams.append('service_id[]', id);
                    });
                    window.history.replaceState({}, '', url.toString());
                    ALL_LOADER_IDS.forEach(showLoader);
                    setSummaryPlaceholders();
                    clearWorkloadTable();
                    clearSupervisorsTable();
                    if (streamApi && typeof streamApi.reconnect === 'function') {
                        streamApi.reconnect();
                    } else if (baseUrls && typeof baseUrls === 'object' && Object.keys(baseUrls).length > 0) {
                        loadAllMetrics(baseUrls, ids);
                    }
                });
            }

            var initialServiceIds = initialServiceIdsFromServer.length
                ? initialServiceIdsFromServer.slice()
                : getMetricsServiceIdsFromDom(filterEl);
            if (typeof window.horizonHubMetricsStreamUrl === 'string' && window.horizonHubMetricsStreamUrl) {
                if (typeof window.__horizonHubMetricsStreamClose === 'function') {
                    window.__horizonHubMetricsStreamClose();
                }
                streamApi = startMetricsStream(
                    window.horizonHubMetricsStreamUrl,
                    function () { return getMetricsServiceIdsFromDom(filterEl); },
                    applyMetricsPayload,
                    initialServiceIds.length ? initialServiceIds : null
                );
                window.__horizonHubMetricsStreamClose = streamApi.close;
            } else {
                loadAllMetrics(baseUrls, initialServiceIds);
            }
        },
    };
}
