/**
 * Initial backoff time for refreshing the stream.
 * @type {number}
 */
var BACKOFF_INITIAL_MS = 1000;
/**
 * Maximum backoff time for refreshing the stream.
 * @type {number}
 */
var BACKOFF_MAX_MS = 30000;
/**
 * Backoff multiplier for refreshing the stream.
 * @type {number}
 */
var BACKOFF_MULTIPLIER = 2;

/**
 * Check if hot reload is enabled.
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
 * Get the stream mode.
 * @returns {string}
 */
function getStreamMode() {
    if (typeof window === 'undefined') return 'refresh';
    var mode = window.horizonHubStreamMode;
    return typeof mode === 'string' && mode ? mode : 'refresh';
}

/**
 * Check if the refresh stream should be used.
 * @returns {boolean}
 */
function shouldUseRefreshStream() {
    return getStreamMode() === 'refresh';
}

/**
 * Number of retries for refreshing the stream.
 * @type {number}
 */
var _refreshStreamRetries = 0;
/**
 * Maximum number of retries for refreshing the stream.
 * @type {number}
 */
var _refreshStreamMaxRetries = 100;

/**
 * Start the refresh stream.
 * @returns {void}
 */
function startRefreshStream() {
    if (typeof window === 'undefined') return;
    var streamUrl = window.horizonHubStreamUrl;
    if (!streamUrl) {
        if (_refreshStreamRetries < _refreshStreamMaxRetries) {
            _refreshStreamRetries += 1;
            setTimeout(startRefreshStream, 50);
        }
        return;
    }
    _refreshStreamRetries = 0;
    if (!shouldUseRefreshStream()) return;
    if (!isHotReloadEnabled()) return;

    var eventSource = null;
    var reconnectTimeout = null;
    var backoffMs = BACKOFF_INITIAL_MS;

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
     * Handle the refresh event.
     * @param {object} eventData
     * @returns {void}
     */
    function onRefresh(eventData) {
        backoffMs = BACKOFF_INITIAL_MS;
        if (typeof document === 'undefined' || document.visibilityState !== 'visible') return;
        var doc = null;
        if (eventData && typeof eventData.html === 'string' && eventData.html.length > 0) {
            var parser = new DOMParser();
            doc = parser.parseFromString(eventData.html, 'text/html');
        }
        if (doc) {
            window.dispatchEvent(new CustomEvent('horizonhub-refresh', { detail: { document: doc } }));
            return;
        }
        var url = window.location.href;
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) return null;
            return response.text();
        }).then(function (html) {
            if (!html) return;
            var parser = new DOMParser();
            doc = parser.parseFromString(html, 'text/html');
            window.dispatchEvent(new CustomEvent('horizonhub-refresh', { detail: { document: doc } }));
        }).catch(function () {});
    }

    /**
     * Get the stream URL with the path.
     * @returns {string}
     */
    function getStreamUrlWithPath() {
        var path = typeof window.location !== 'undefined' && window.location.pathname ? window.location.pathname : '';
        if (!path) return streamUrl;
        var sep = streamUrl.indexOf('?') === -1 ? '?' : '&';
        return streamUrl + sep + 'path=' + encodeURIComponent(path);
    }

    /**
     * Connect to the stream.
     * @returns {void}
     */
    function connect() {
        if (!isHotReloadEnabled() || !shouldUseRefreshStream()) return;
        closeStream();
        eventSource = new EventSource(getStreamUrlWithPath());

        eventSource.addEventListener('refresh', function (e) {
            var payload = null;
            if (e.data) {
                try {
                    payload = JSON.parse(e.data);
                } catch (err) {}
            }
            onRefresh(payload);
        });

        eventSource.onerror = function () {
            closeStream();
            reconnectTimeout = setTimeout(function () {
                connect();
                if (backoffMs < BACKOFF_MAX_MS) {
                    backoffMs *= BACKOFF_MULTIPLIER;
                }
            }, backoffMs);
        };

        eventSource.onopen = function () {
            backoffMs = BACKOFF_INITIAL_MS;
        };
    }

    window.addEventListener('horizonhub-hotreload-changed', function (e) {
        if (e.detail && e.detail.enabled === true) {
            connect();
        } else {
            closeStream();
        }
    });

    connect();

    if (window.__horizonHubRefreshStreamClose) {
        window.__horizonHubRefreshStreamClose();
    }
    window.__horizonHubRefreshStreamClose = closeStream;
}

/**
 * Handle the refresh requested event.
 * When refresh is requested without a document, do one fetch
 * and dispatch with document so listeners can update DOM without additional requests.
 * Ensures only one fetch per refresh regardless of how many views listen.
 * @param {Event} e
 * @returns {void}
 */
function onRefreshRequested(e) {
    if (e.detail && e.detail.document) return;
    var url = typeof window !== 'undefined' ? window.location.href : '';
    if (!url) return;
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
    }).then(function (response) {
        if (!response.ok) return null;
        return response.text();
    }).then(function (html) {
        if (!html) return;
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        window.dispatchEvent(new CustomEvent('horizonhub-refresh', { detail: { document: doc } }));
    }).catch(function () {});
}

/**
 * Initialize the refresh stream.
 * @returns {void}
 */
export function initRefreshStream() {
    if (typeof document === 'undefined') return;
    if (!window.__horizonHubRefreshRequestListener) {
        window.__horizonHubRefreshRequestListener = true;
        window.addEventListener('horizonhub-refresh', onRefreshRequested);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startRefreshStream);
    } else {
        startRefreshStream();
    }
}
