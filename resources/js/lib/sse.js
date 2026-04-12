import { connectStreamSource, disconnectStreamSource } from '@hotwired/turbo';

/**
 * Whether Horizon Hub hot reload (SSE refresh) is enabled.
 * @returns {boolean}
 */
export function isHotReloadEnabled() {
    return localStorage.getItem('horizonhub_hotreload') !== 'false';
}

/**
 * @type {number}
 */
export var SSE_BACKOFF_INITIAL_MS = 1000;

/**
 * @type {number}
 */
export var SSE_BACKOFF_MAX_MS = 30000;

/**
 * @type {number}
 */
export var SSE_BACKOFF_MULTIPLIER = 2;

/**
 * @type {number}
 */
var VISIBILITY_RECONNECT_DELAY_MS = 200;

/**
 * @type {EventSource|null}
 */
var _eventSource = null;

/**
 * @type {number|null}
 */
var _reconnectTimeout = null;

/**
 * @type {number}
 */
var _maxReconnectAttempts = 2;

/**
 * @type {number}
 */
var _reconnectAttempts = 0;

/**
 * @type {number}
 */
var _backoffMs = SSE_BACKOFF_INITIAL_MS;

/**
 * @type {number|null}
 */
var _visibilityTimeout = null;

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
    if (_eventSource) {
        disconnectStreamSource(_eventSource);
        _eventSource.close();
        _eventSource = null;
    }
}

/**
 * Open an SSE connection and register it with Turbo for automatic stream processing.
 * @returns {void}
 */
function openStream() {
    if (!isHotReloadEnabled()) return;
    closeStream();
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
        _reconnectTimeout = setTimeout(function () {
            openStream();
            if (_backoffMs < SSE_BACKOFF_MAX_MS) _backoffMs *= SSE_BACKOFF_MULTIPLIER;
        }, _backoffMs);
    };

    _eventSource.onopen = function () {
        _backoffMs = SSE_BACKOFF_INITIAL_MS;
    };
}

/**
 * Handle hot-reload toggle from sidebar switch.
 * @param {CustomEvent} e
 * @returns {void}
 */
function onHotReloadChanged(e) {
    if (e.detail && e.detail.enabled === true) {
        openStream();
    } else {
        closeStream();
    }
}

/**
 * Pause/resume stream based on tab visibility.
 * @returns {void}
 */
function onVisibilityChange() {
    if (typeof document === 'undefined') return;
    if (document.visibilityState === 'hidden') {
        closeStream();
        if (_visibilityTimeout) {
            clearTimeout(_visibilityTimeout);
            _visibilityTimeout = null;
        }
    } else if (document.visibilityState === 'visible') {
        if (_visibilityTimeout) return;
        _visibilityTimeout = setTimeout(function () {
            _visibilityTimeout = null;
            if (typeof document !== 'undefined' && document.visibilityState === 'visible' && isHotReloadEnabled()) {
                openStream();
            }
        }, VISIBILITY_RECONNECT_DELAY_MS);
    }
}

/**
 * Initialize the Turbo Stream SSE connection.
 * @returns {void}
 */
export function initTurboStream() {
    window.addEventListener('horizonhub-hotreload-changed', onHotReloadChanged);
    document.addEventListener('visibilitychange', onVisibilityChange);

    window.__horizonHubRefreshStreamClose = closeStream;
    window.__horizonHubRefreshStreamReconnect = function () {
        closeStream();
        if (isHotReloadEnabled()) {
            openStream();
        }
    };

    document.addEventListener('turbo:before-visit', function () {
        closeStream();
    });
    document.addEventListener('turbo:load', function () {
        if (typeof window.__horizonHubRefreshStreamReconnect === 'function') {
            window.__horizonHubRefreshStreamReconnect();
        }
    });

    openStream();
}
