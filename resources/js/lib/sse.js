import { connectStreamSource, disconnectStreamSource } from '@hotwired/turbo';

/**
 * Whether Horizon Hub hot reload (SSE refresh) is enabled.
 * @returns {boolean}
 */
export function isHotReloadEnabled() {
    return localStorage.getItem('horizonhub_hotreload') !== 'false';
}

/**
 * @type {number|null}
 */
var _reconnectDebounceTimer = null;

/**
 * Initialize the Turbo Stream SSE connection.
 * @returns {void}
 */
export function initTurboStream() {
    window.addEventListener('horizonhub-hotreload-changed', function () {
        closeStream();
        openStream();
    });
    document.addEventListener('turbo:before-visit', function () {
        closeStream();
    });
    document.addEventListener('turbo:load', function () {
        scheduleStreamReconnect();
    });
    scheduleStreamReconnect();
}

/**
 * @type {number}
 */
var SSE_BACKOFF_INITIAL_MS = 1000;

/**
 * @type {number}
 */
var SSE_BACKOFF_MAX_MS = 30000;

/**
 * @type {number}
 */
var SSE_BACKOFF_MULTIPLIER = 2;

/**
 * @type {EventSource|null}
 */
var _eventSource = null;

/**
 * @type {number|null}
 */
var _reconnectTimeout = null;

/**
 * @type {number|null}
 */
var _oneShotSafetyTimer = null;

/**
 * @type {number}
 */
var _maxReconnectAttempts = 12;

/**
 * @type {number}
 */
var _reconnectAttempts = 0;

/**
 * @type {number}
 */
var _backoffMs = SSE_BACKOFF_INITIAL_MS;

/**
 * Build the SSE URL for the current page path and query params.
 * @returns {string|null}
 */
function buildStreamUrl() {
    var base = typeof window !== 'undefined' ? window.horizonHubStreamsBaseUrl : null;
    if (!base) return null;
    var pathname = typeof window.location !== 'undefined' && window.location.pathname ? window.location.pathname : '';
    var url = String(base).replace(/\/$/, '') + pathname;
    var search = typeof window.location !== 'undefined' && window.location.search ? window.location.search : '';
    if (search.length > 1) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + search.substring(1);
    }
    return url;
}

/**
 * Close the active SSE stream and disconnect from Turbo.
 * @returns {void}
 */
function closeStream() {
    if (_reconnectTimeout) {
        clearTimeout(_reconnectTimeout);
        _reconnectTimeout = null;
    }
    if (_oneShotSafetyTimer) {
        clearTimeout(_oneShotSafetyTimer);
        _oneShotSafetyTimer = null;
    }
    if (_eventSource) {
        disconnectStreamSource(_eventSource);
        _eventSource.close();
        _eventSource = null;
    }
}

/**
 * Debounce reconnect so turbo:load + init do not open duplicate SSE sockets.
 * @returns {void}
 */
function scheduleStreamReconnect() {
    if (_reconnectDebounceTimer) {
        clearTimeout(_reconnectDebounceTimer);
    }
    _reconnectDebounceTimer = window.setTimeout(function () {
        _reconnectDebounceTimer = null;
        closeStream();
        openStream();
    }, 50);
}

/**
 * Open an SSE connection and register it with Turbo for automatic stream processing.
 * @returns {void}
 */
function openStream() {
    if (!isHotReloadEnabled()) {
        openStreamOneShot();
        return;
    }
    var url = buildStreamUrl();
    if (!url) return;
    _eventSource = new EventSource(url);
    connectStreamSource(_eventSource);

    _eventSource.onerror = function () {
        closeStream();
        _reconnectAttempts++;
        if (_reconnectAttempts >= _maxReconnectAttempts) {
            return;
        }
        _reconnectTimeout = window.setTimeout(function () {
            openStream();
            if (_backoffMs < SSE_BACKOFF_MAX_MS) _backoffMs *= SSE_BACKOFF_MULTIPLIER;
        }, _backoffMs);
    };

    _eventSource.onopen = function () {
        _backoffMs = SSE_BACKOFF_INITIAL_MS;
        _reconnectAttempts = 0;
    };
}

/**
 * One SSE connection: apply the first turbo-stream payload, then close (hot reload off).
 * @returns {void}
 */
function openStreamOneShot() {
    var url = buildStreamUrl();
    if (!url) return;

    var finished = false;

    function cleanupOneShot() {
        if (finished) return;
        finished = true;
        closeStream();
    }

    _eventSource = new EventSource(url);
    connectStreamSource(_eventSource);

    _eventSource.onmessage = function () {
        cleanupOneShot();
    };

    _eventSource.onerror = function () {
        cleanupOneShot();
    };

    _oneShotSafetyTimer = window.setTimeout(cleanupOneShot, SSE_BACKOFF_MAX_MS);
}
