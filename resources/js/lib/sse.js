import { dispatchHorizonHubRefreshWithDocument } from './dom';
import { fetchCurrentPageAsDocument } from './http';

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
 * EventSource with exponential backoff reconnect, hot-reload toggle, and visibility pause/resume.
 * @param {object} opt
 * @param {function(): string} opt.getUrl
 * @param {function(): boolean} opt.shouldConnect
 * @param {function(EventSource, { thisConnectToken?: number, resetBackoff: function(): void }): void} opt.registerEventHandlers
 * @param {function(): (number|void)} [opt.onBeforeEachConnect]
 * @param {boolean} [opt.enableVisibilityResume=true]
 * @param {boolean} [opt.enableHotReloadToggle=true]
 * @returns {{ connect: function(): void, closeStream: function(): void, destroy: function(): void }}
 */
export function createReconnectingEventSourceSession(opt) {
    var eventSource = null;
    var reconnectTimeout = null;
    var backoffMs = SSE_BACKOFF_INITIAL_MS;
    var enableVis = opt.enableVisibilityResume !== false;
    var enableHot = opt.enableHotReloadToggle !== false;

    /**
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
     * @returns {void}
     */
    function connect() {
        if (!opt.shouldConnect()) return;
        closeStream();
        var token;
        if (typeof opt.onBeforeEachConnect === 'function') {
            token = opt.onBeforeEachConnect();
        }
        var url = opt.getUrl();
        if (!url) return;
        eventSource = new EventSource(url);

        if (typeof opt.registerEventHandlers === 'function') {
            opt.registerEventHandlers(eventSource, {
                thisConnectToken: token,
                resetBackoff: function () {
                    backoffMs = SSE_BACKOFF_INITIAL_MS;
                },
            });
        }

        eventSource.onerror = function () {
            closeStream();
            reconnectTimeout = setTimeout(function () {
                connect();
                if (backoffMs < SSE_BACKOFF_MAX_MS) backoffMs *= SSE_BACKOFF_MULTIPLIER;
            }, backoffMs);
        };

        eventSource.onopen = function () {
            backoffMs = SSE_BACKOFF_INITIAL_MS;
        };
    }

    /**
     * @param {CustomEvent} e
     * @returns {void}
     */
    function onHotReloadChanged(e) {
        if (e.detail && e.detail.enabled === true) {
            connect();
        } else {
            closeStream();
        }
    }

    var visibilityReconnectTimeout = null;

    /**
     * @returns {void}
     */
    function scheduleReconnectOnVisible() {
        if (!enableVis) return;
        if (visibilityReconnectTimeout) return;
        visibilityReconnectTimeout = setTimeout(function () {
            visibilityReconnectTimeout = null;
            if (typeof document === 'undefined') return;
            if (document.visibilityState === 'visible' && opt.shouldConnect()) {
                connect();
            }
        }, VISIBILITY_RECONNECT_DELAY_MS);
    }

    /**
     * @returns {void}
     */
    function onVisibilityChange() {
        if (!enableVis || typeof document === 'undefined') return;
        if (document.visibilityState === 'hidden') {
            closeStream();
            if (visibilityReconnectTimeout) {
                clearTimeout(visibilityReconnectTimeout);
                visibilityReconnectTimeout = null;
            }
        } else if (document.visibilityState === 'visible') {
            scheduleReconnectOnVisible();
        }
    }

    if (enableHot && typeof window !== 'undefined') {
        window.addEventListener('horizonhub-hotreload-changed', onHotReloadChanged);
    }
    if (enableVis && typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', onVisibilityChange);
    }

    return {
        connect: connect,
        closeStream: closeStream,
        destroy: function () {
            if (enableHot && typeof window !== 'undefined') {
                window.removeEventListener('horizonhub-hotreload-changed', onHotReloadChanged);
            }
            if (enableVis && typeof document !== 'undefined') {
                document.removeEventListener('visibilitychange', onVisibilityChange);
            }
            closeStream();
        },
    };
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

    /**
     * Get the stream URL with the path and current page query (so server fetches same filters).
     * @returns {string}
     */
    function getStreamUrlWithPath() {
        var path = typeof window.location !== 'undefined' && window.location.pathname ? window.location.pathname : '';
        if (!path) return streamUrl;
        var sep = streamUrl.indexOf('?') === -1 ? '?' : '&';
        var url = streamUrl + sep + 'path=' + encodeURIComponent(path);
        var search = typeof window.location !== 'undefined' && window.location.search ? window.location.search : '';
        if (search.length > 1) {
            url += '&query=' + encodeURIComponent(search.substring(1));
        }
        return url;
    }

    /**
     * @param {object|null} eventData
     * @param {function(): void} resetBackoff
     * @returns {void}
     */
    function onRefresh(eventData, resetBackoff) {
        resetBackoff();
        if (typeof document === 'undefined' || document.visibilityState !== 'visible') return;
        var doc = null;
        if (eventData && typeof eventData.html === 'string' && eventData.html.length > 0) {
            var parser = new DOMParser();
            doc = parser.parseFromString(eventData.html, 'text/html');
        }
        if (doc) {
            dispatchHorizonHubRefreshWithDocument(doc);
            return;
        }
        fetchCurrentPageAsDocument().then(function (fetched) {
            if (fetched) dispatchHorizonHubRefreshWithDocument(fetched);
        });
    }

    var session = createReconnectingEventSourceSession({
        getUrl: getStreamUrlWithPath,
        shouldConnect: function () {
            return isHotReloadEnabled() && shouldUseRefreshStream();
        },
        registerEventHandlers: function (es, api) {
            es.addEventListener('refresh', function (e) {
                var payload = null;
                if (e.data) {
                    try {
                        payload = JSON.parse(e.data);
                    } catch (err) {}
                }
                onRefresh(payload, api.resetBackoff);
            });
        },
    });

    session.connect();

    if (window.__horizonHubRefreshStreamClose) {
        window.__horizonHubRefreshStreamClose();
    }
    window.__horizonHubRefreshStreamClose = session.closeStream;
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
    fetchCurrentPageAsDocument().then(function (doc) {
        if (doc) dispatchHorizonHubRefreshWithDocument(doc);
    });
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
